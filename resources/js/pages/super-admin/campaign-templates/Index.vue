<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    Gift,
    Plus,
    Sparkles,
    Trash2,
    Pencil,
    Zap,
} from 'lucide-vue-next';
import { ref } from 'vue';
import {
    PageHeader,
    StatCard,
    StatusBadge,
    EmptyState,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    templates: Array<{
        id: number;
        name: string;
        slug: string;
        season: string;
        description: string | null;
        discount_type: string;
        discount_value: number;
        default_duration_days: number;
        default_budget_cap: number | null;
        default_max_uses: number | null;
        code_prefix: string;
        theme_color: string | null;
        is_active: boolean;
        batches_count: number;
    }>;
    seasons: Record<string, string>;
}>();

const seasonColors: Record<string, string> = {
    tet: 'from-red-500/20 to-amber-500/10 border-red-500/30',
    valentine: 'from-pink-500/20 to-rose-500/10 border-pink-500/30',
    women_day_8_3: 'from-fuchsia-500/20 to-pink-500/10 border-fuchsia-500/30',
    women_day_20_10: 'from-violet-500/20 to-purple-500/10 border-violet-500/30',
    mid_autumn: 'from-amber-500/20 to-yellow-500/10 border-amber-500/30',
    national_day: 'from-red-600/20 to-yellow-500/10 border-red-600/30',
    black_friday: 'from-slate-800/30 to-slate-600/10 border-slate-700/40',
    noel: 'from-emerald-500/20 to-red-500/10 border-emerald-500/30',
    custom: 'from-sky-500/20 to-indigo-500/10 border-sky-500/30',
};

const seasonEmojis: Record<string, string> = {
    tet: '🧧',
    valentine: '💝',
    women_day_8_3: '💐',
    women_day_20_10: '🌹',
    mid_autumn: '🥮',
    national_day: '🇻🇳',
    black_friday: '🏷️',
    noel: '🎄',
    custom: '⚡',
};

const showCreate = ref(false);
const showGenerate = ref(false);
const generateTarget = ref<any>(null);

const form = useForm({
    name: '',
    slug: '',
    season: 'custom',
    description: '',
    discount_type: 'percent',
    discount_value: 10,
    default_duration_days: 7,
    default_budget_cap: '',
    default_max_uses: '',
    code_prefix: '',
    theme_color: '#6366f1',
});

const generateForm = useForm({
    code_count: 100,
    starts_at: '',
    expires_at: '',
});

function submitCreate() {
    form.post('/super-admin/campaign-templates', {
        onSuccess: () => {
            showCreate.value = false;
            form.reset();
        },
    });
}

function openGenerate(template: any) {
    generateTarget.value = template;
    generateForm.reset();
    showGenerate.value = true;
}

function submitGenerate() {
    if (!generateTarget.value) {
        return;
    }

    generateForm.post(
        `/super-admin/campaign-templates/${generateTarget.value.id}/generate`,
        {
            onSuccess: () => {
                showGenerate.value = false;
            },
        },
    );
}

async function deleteTemplate(id: number) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Xóa template này?',
        }))
    ) {
        return;
    }

    router.delete(`/super-admin/campaign-templates/${id}`);
}
</script>

<template>
    <Head title="Chiến dịch theo mùa" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Chiến dịch Khuyến mãi theo Mùa"
            subtitle="Tạo template sẵn cho các dịp lễ, tự động generate mã coupon hàng loạt."
            :icon="Calendar"
        >
            <template #actions>
                <Button @click="showCreate = true" class="gap-1.5">
                    <Plus class="size-4" /> Tạo template mới
                </Button>
            </template>
        </PageHeader>

        <!-- Template Gallery -->
        <div
            v-if="templates.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="t in templates"
                :key="t.id"
                :class="[
                    'group relative overflow-hidden rounded-2xl border bg-gradient-to-br p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-lg',
                    seasonColors[t.season] ?? seasonColors.custom,
                ]"
            >
                <div class="flex items-start justify-between">
                    <div class="text-3xl">
                        {{ seasonEmojis[t.season] ?? '⚡' }}
                    </div>
                    <StatusBadge
                        :status="t.is_active ? 'active' : 'inactive'"
                        size="sm"
                    >
                        {{ t.is_active ? 'Active' : 'Tắt' }}
                    </StatusBadge>
                </div>
                <h3 class="mt-3 text-base font-bold text-foreground">
                    {{ t.name }}
                </h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ seasons[t.season] ?? t.season }}
                </p>

                <div class="mt-3 flex items-center gap-2">
                    <span
                        class="rounded-md bg-card/80 px-2 py-1 font-mono text-xs font-bold text-primary"
                    >
                        {{
                            t.discount_type === 'percent'
                                ? `${t.discount_value}%`
                                : `${t.discount_value}₫`
                        }}
                    </span>
                    <span class="text-[10px] text-muted-foreground"
                        >{{ t.default_duration_days }} ngày</span
                    >
                    <span class="font-mono text-[10px] text-muted-foreground"
                        >{{ t.code_prefix }}-XXX</span
                    >
                </div>

                <p
                    v-if="t.description"
                    class="mt-2 line-clamp-2 text-xs text-muted-foreground"
                >
                    {{ t.description }}
                </p>

                <div
                    class="mt-4 flex items-center justify-between border-t border-border/30 pt-3"
                >
                    <span class="text-[10px] text-muted-foreground"
                        >{{ t.batches_count }} batch đã tạo</span
                    >
                    <div class="flex gap-1">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            @click="openGenerate(t)"
                            title="Tạo mã"
                        >
                            <Zap class="size-3.5 text-primary" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            @click="deleteTemplate(t.id)"
                            title="Xóa"
                        >
                            <Trash2 class="size-3.5 text-rose-500" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState
            v-else
            :icon="Gift"
            title="Chưa có template chiến dịch nào"
            description="Tạo template cho các dịp lễ như Tết, Valentine, Black Friday để generate mã coupon hàng loạt."
        >
            <template #action>
                <Button @click="showCreate = true" class="gap-1.5"
                    ><Plus class="size-4" /> Tạo template</Button
                >
            </template>
        </EmptyState>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:open="showCreate">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Tạo template chiến dịch mới</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="grid gap-4 py-2">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Tên chiến dịch *</Label>
                        <Input
                            v-model="form.name"
                            placeholder="Khuyến mãi Tết 2027"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Slug *</Label>
                        <Input
                            v-model="form.slug"
                            placeholder="tet-2027"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Mùa/Dịp *</Label>
                        <Select v-model="form.season">
                            <SelectTrigger
                                ><SelectValue placeholder="Chọn..."
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="(label, key) in seasons"
                                    :key="key"
                                    :value="key"
                                    >{{ label }}</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Loại giảm giá</Label>
                        <Select v-model="form.discount_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="percent"
                                    >Phần trăm (%)</SelectItem
                                >
                                <SelectItem value="fixed"
                                    >Số tiền cố định (₫)</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Giá trị giảm</Label>
                        <Input
                            v-model.number="form.discount_value"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Tiền tố mã *</Label>
                        <Input
                            v-model="form.code_prefix"
                            placeholder="TET"
                            maxlength="10"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Thời hạn (ngày)</Label>
                        <Input
                            v-model.number="form.default_duration_days"
                            type="number"
                            min="1"
                        />
                    </div>
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Mô tả</Label>
                        <Input
                            v-model="form.description"
                            placeholder="Mô tả chiến dịch..."
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showCreate = false"
                        >Hủy</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Tạo template</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Generate Dialog -->
    <Dialog v-model:open="showGenerate">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle
                    >Tạo mã từ "{{ generateTarget?.name }}"</DialogTitle
                >
            </DialogHeader>
            <form @submit.prevent="submitGenerate" class="grid gap-4 py-2">
                <div class="grid gap-1.5">
                    <Label>Số lượng mã (1-1000)</Label>
                    <Input
                        v-model.number="generateForm.code_count"
                        type="number"
                        min="1"
                        max="1000"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label>Hiệu lực từ</Label>
                    <Input v-model="generateForm.starts_at" type="date" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Hết hạn</Label>
                    <Input v-model="generateForm.expires_at" type="date" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showGenerate = false"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="generateForm.processing"
                        class="gap-1.5"
                    >
                        <Zap class="size-4" /> Tạo
                        {{ generateForm.code_count }} mã
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
