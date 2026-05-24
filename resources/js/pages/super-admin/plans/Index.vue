<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Crown,
    Building2,
    CheckCircle2,
    XCircle,
    Edit2,
    Save,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

defineProps<{
    plans: Array<{
        id: number;
        code: string;
        name: string;
        price: number;
        billing_cycle: string;
        max_branches: number;
        max_tables: number;
        max_users: number;
        features: Record<string, any>;
        status: string;
        restaurants_count: number;
    }>;
}>();

const editingId = ref<number | null>(null);

function startEdit(plan: any) {
    editingId.value = plan.id;
    form.name = plan.name;
    form.price = plan.price;
    form.max_branches = plan.max_branches;
    form.max_tables = plan.max_tables;
    form.max_users = plan.max_users;
    form.max_areas = plan.features?.max_areas ?? 2;
    form.max_storage_mb = plan.features?.max_storage_mb ?? 500;
    form.ai_features = plan.features?.ai_features ?? false;
    form.realtime = plan.features?.realtime ?? false;
    form.advanced_analytics = plan.features?.advanced_analytics ?? false;
    form.api_rate_limit = plan.features?.api_rate_limit ?? 60;
}

const form = useForm({
    name: '',
    price: 0,
    max_branches: 1,
    max_tables: 10,
    max_users: 5,
    max_areas: 2,
    max_storage_mb: 500,
    ai_features: false,
    realtime: false,
    advanced_analytics: false,
    api_rate_limit: 60,
});

function save(planId: number) {
    form.patch(`/super-admin/plans/${planId}`, {
        onSuccess: () => {
            editingId.value = null;
        },
    });
}

function formatLimit(v: number | null | undefined) {
    return v === null || v === undefined || v === -1
        ? 'Không giới hạn'
        : String(v);
}
function formatVnd(v: number) {
    return v === 0
        ? 'Miễn phí'
        : new Intl.NumberFormat('vi-VN').format(v) + ' VND/tháng';
}
</script>

<template>
    <Head title="Quản lý gói dịch vụ" />

    <div class="flex flex-col gap-6 p-6">
        <div>
            <h1 class="text-2xl font-bold">Gói dịch vụ</h1>
            <p class="text-sm text-muted-foreground">
                Cấu hình giới hạn tài nguyên cho từng gói
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card v-for="plan in plans" :key="plan.id">
                <CardHeader class="pb-3">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2">
                            <Crown
                                v-if="plan.code === 'pro'"
                                class="size-5 text-purple-500"
                            />
                            <Building2 v-else class="size-5 text-blue-500" />
                            {{ plan.name }}
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >({{ plan.code }})</span
                            >
                        </CardTitle>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground"
                                >{{ plan.restaurants_count }} nhà hàng</span
                            >
                            <Button
                                v-if="editingId !== plan.id"
                                variant="ghost"
                                size="icon-sm"
                                @click="startEdit(plan)"
                            >
                                <Edit2 class="size-4" />
                            </Button>
                            <Button
                                v-else
                                variant="ghost"
                                size="icon-sm"
                                @click="save(plan.id)"
                                :disabled="form.processing"
                            >
                                <Save class="size-4 text-green-600" />
                            </Button>
                        </div>
                    </div>
                    <p
                        :class="[
                            'text-lg font-bold',
                            plan.code === 'pro'
                                ? 'text-purple-600'
                                : 'text-muted-foreground',
                        ]"
                    >
                        {{ formatVnd(plan.price) }}
                    </p>
                </CardHeader>

                <CardContent class="flex flex-col gap-4">
                    <!-- View mode -->
                    <template v-if="editingId !== plan.id">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded border p-2.5">
                                <p class="text-xs text-muted-foreground">
                                    Chi nhánh
                                </p>
                                <p class="font-bold">
                                    {{ formatLimit(plan.max_branches) }}
                                </p>
                            </div>
                            <div class="rounded border p-2.5">
                                <p class="text-xs text-muted-foreground">
                                    Bàn ăn
                                </p>
                                <p class="font-bold">
                                    {{ formatLimit(plan.max_tables) }}
                                </p>
                            </div>
                            <div class="rounded border p-2.5">
                                <p class="text-xs text-muted-foreground">
                                    Nhân viên
                                </p>
                                <p class="font-bold">
                                    {{ formatLimit(plan.max_users) }}
                                </p>
                            </div>
                            <div class="rounded border p-2.5">
                                <p class="text-xs text-muted-foreground">
                                    Khu vực
                                </p>
                                <p class="font-bold">
                                    {{
                                        formatLimit(
                                            plan.features?.max_areas ?? 2,
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="rounded border p-2.5">
                                <p class="text-xs text-muted-foreground">
                                    Lưu trữ
                                </p>
                                <p class="font-bold">
                                    {{ plan.features?.max_storage_mb ?? 500 }}
                                    MB
                                </p>
                            </div>
                            <div class="rounded border p-2.5">
                                <p class="text-xs text-muted-foreground">
                                    Rate limit
                                </p>
                                <p class="font-bold">
                                    {{
                                        plan.features?.api_rate_limit ?? 60
                                    }}/phút
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="feat in [
                                    'ai_features',
                                    'realtime',
                                    'advanced_analytics',
                                ]"
                                :key="feat"
                                :class="[
                                    'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium',
                                    plan.features?.[feat]
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30'
                                        : 'bg-muted text-muted-foreground line-through',
                                ]"
                            >
                                <CheckCircle2
                                    v-if="plan.features?.[feat]"
                                    class="size-3"
                                />
                                <XCircle v-else class="size-3" />
                                {{ feat.replace(/_/g, ' ') }}
                            </span>
                        </div>
                    </template>

                    <!-- Edit mode -->
                    <template v-else>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2 grid gap-1.5">
                                <Label class="text-xs">Tên gói</Label>
                                <Input v-model="form.name" size="sm" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs">Giá (VND/tháng)</Label>
                                <Input
                                    v-model.number="form.price"
                                    type="number"
                                    min="0"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs"
                                    >Rate limit (req/phút)</Label
                                >
                                <Input
                                    v-model.number="form.api_rate_limit"
                                    type="number"
                                    min="10"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs"
                                    >Max chi nhánh (-1 = ∞)</Label
                                >
                                <Input
                                    v-model.number="form.max_branches"
                                    type="number"
                                    min="-1"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs">Max bàn (-1 = ∞)</Label>
                                <Input
                                    v-model.number="form.max_tables"
                                    type="number"
                                    min="-1"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs"
                                    >Max nhân viên (-1 = ∞)</Label
                                >
                                <Input
                                    v-model.number="form.max_users"
                                    type="number"
                                    min="-1"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs"
                                    >Max khu vực (-1 = ∞)</Label
                                >
                                <Input
                                    v-model.number="form.max_areas"
                                    type="number"
                                    min="-1"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label class="text-xs">Storage (MB)</Label>
                                <Input
                                    v-model.number="form.max_storage_mb"
                                    type="number"
                                    min="1"
                                />
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 text-sm">
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="form.ai_features"
                                    class="rounded"
                                />
                                Tính năng AI
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="form.realtime"
                                    class="rounded"
                                />
                                Realtime
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    v-model="form.advanced_analytics"
                                    class="rounded"
                                />
                                Phân tích nâng cao
                            </label>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                size="sm"
                                @click="save(plan.id)"
                                :disabled="form.processing"
                            >
                                {{
                                    form.processing
                                        ? 'Đang lưu...'
                                        : 'Lưu thay đổi'
                                }}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="editingId = null"
                                >Hủy</Button
                            >
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
