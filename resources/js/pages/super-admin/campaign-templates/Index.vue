<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    Gift,
    Plus,
    Sparkles,
    Trash2,
    Zap,
    Tag,
    Link as LinkIcon,
    Percent,
    Clock,
    CheckCircle2,
    FileText,
    DollarSign,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import {
    PageHeader,
    SectionCard,
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
        total_codes?: number;
        total_usages?: number;
    }>;
    seasons: Record<string, string>;
}>();

const seasonColors: Record<string, string> = {
    tet: 'border-border/60 bg-card/80',
    valentine: 'border-border/60 bg-card/80',
    women_day_8_3: 'border-border/60 bg-card/80',
    women_day_20_10: 'border-border/60 bg-card/80',
    mid_autumn: 'border-border/60 bg-card/80',
    national_day: 'border-border/60 bg-card/80',
    black_friday: 'border-border/60 bg-card/80',
    noel: 'border-border/60 bg-card/80',
    custom: 'border-border/60 bg-card/80',
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

const currentMonth = new Date().getMonth() + 1; // 1-12
const currentYear = new Date().getFullYear();

const aiRecommendation = computed(() => {
    if (currentMonth === 11 || currentMonth === 12) {
        return {
            title: 'Lên kế hoạch Tết Nguyên Đán',
            text: `Hôm nay là cuối năm ${currentYear}. Tết Nguyên Đán sắp diễn ra trong khoảng 1-2 tháng tới. AI khuyên bạn khởi tạo chiến dịch khuyến mãi Tết với mức giảm 20% (Tiền tố TET) để kích cầu doanh thu tối đa trong dịp lễ lớn này.`,
            season: 'tet',
        };
    } else if (currentMonth === 1 || currentMonth === 2) {
        return {
            title: 'Chiến dịch Valentine ngọt ngào',
            text: 'Dịp lễ Valentine (14/2) đang cận kề. Đây là cơ hội vàng để tung gói khuyến mãi cố định (ví dụ: Giảm 100k - Tiền tố VAL) nhắm tới các thương hiệu F&B muốn mở rộng kênh bán hàng trực tuyến.',
            season: 'valentine',
        };
    } else if (currentMonth >= 8 && currentMonth <= 9) {
        return {
            title: 'Chiến dịch Thu - Giáng Sinh sớm',
            text: 'Tháng 8-9 là giai đoạn chuẩn bị cho mùa lễ hội cuối năm. AI gợi ý chuẩn bị trước chiến dịch Halloween hoặc thiết lập sớm mã giảm giá Trung Thu để thúc đẩy đăng ký mới.',
            season: 'mid_autumn',
        };
    } else if (currentMonth === 10) {
        return {
            title: 'Black Friday & Noel đang đến gần',
            text: `Sắp tới tháng 11 với ngày hội mua sắm Black Friday lớn nhất năm. AI khuyến nghị bạn chuẩn bị cấu hình sẵn chiến dịch Black Friday với mức chiết khấu sâu (25% - 30%) để thu hút các đối tác F&B lớn nâng cấp gói dịch vụ dài hạn.`,
            season: 'black-friday',
        };
    } else {
        // Mặc định hoặc mùa hè
        return {
            title: 'Tối ưu hóa các chiến dịch giữa năm',
            text: 'Giai đoạn giữa năm là thời điểm thích hợp để chạy các chương trình khuyến mãi nhỏ kích cầu. AI gợi ý tạo một template chiến dịch tùy chỉnh (Custom) với mức giảm giá 10% - 15% để duy trì tăng trưởng thuê bao ổn định.',
            season: 'custom',
        };
    }
});

const templateSummary = computed(() => ({
    total: props.templates.length,
    active: props.templates.filter((template) => template.is_active).length,
    batches: props.templates.reduce(
        (total, template) => total + template.batches_count,
        0,
    ),
    codes: props.templates.reduce(
        (total, template) => total + (template.total_codes ?? 0),
        0,
    ),
}));

function applyAiRecommendation() {
    const rec = aiRecommendation.value;
    form.reset();
    form.season = rec.season;

    if (rec.season === 'tet') {
        form.name = `Khuyến mãi Tết Nguyên Đán ${currentYear + 1}`;
        form.slug = `tet-nguyen-dan-${currentYear + 1}`;
        form.discount_type = 'percent';
        form.discount_value = 20;
        form.default_duration_days = 14;
        form.code_prefix = 'TET';
        form.description = `Chiến dịch tri ân đối tác dịp Tết Nguyên Đán với mã giảm 20% cho tất cả các gói dịch vụ.`;
    } else if (rec.season === 'valentine') {
        form.name = `Chiến dịch Valentine ngọt ngào ${currentYear}`;
        form.slug = `valentine-love-${currentYear}`;
        form.discount_type = 'fixed';
        form.discount_value = 100000;
        form.default_duration_days = 5;
        form.code_prefix = 'VAL';
        form.description = `Mã giảm giá cố định 100,000₫ cho các cửa hàng đăng ký/gia hạn gói dịch vụ Pro trở lên.`;
    } else if (rec.season === 'black-friday') {
        form.name = `Siêu ưu đãi Black Friday ${currentYear}`;
        form.slug = `black-friday-${currentYear}`;
        form.discount_type = 'percent';
        form.discount_value = 30;
        form.default_duration_days = 3;
        form.code_prefix = 'BFRI';
        form.description = `Đợt giảm giá sâu nhất trong năm lên tới 30% dịp Black Friday ${currentYear} cho khách hàng đăng ký hoặc gia hạn dài hạn.`;
    } else {
        form.name = `Khuyến mãi Hè ${currentYear}`;
        form.slug = `uu-dai-he-${currentYear}`;
        form.discount_type = 'percent';
        form.discount_value = 10;
        form.default_duration_days = 7;
        form.code_prefix = 'SUMMER';
        form.description = `Chương trình khuyến mãi mùa hè kích cầu tăng trưởng thuê bao giữa năm ${currentYear}.`;
    }

    showCreate.value = true;
}

function seedDefaults() {
    router.post('/super-admin/campaign-templates/seed-defaults');
}

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
    <Head title="Chiến dịch khuyến mãi theo mùa" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Chiến dịch khuyến mãi theo mùa"
            subtitle="Tạo mẫu chiến dịch cho các dịp lễ và sinh mã giảm giá hàng loạt."
            :icon="Calendar"
        >
            <template #actions>
                <Button
                    @click="seedDefaults"
                    variant="outline"
                    class="gap-1.5 border-amber-500/30 text-amber-500 hover:bg-amber-500/10"
                >
                    <Sparkles class="size-4 animate-pulse text-amber-500" />
                    Khởi tạo bản mẫu
                </Button>
                <Button @click="showCreate = true" class="gap-1.5">
                    <Plus class="size-4" /> Tạo mẫu mới
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Tổng số mẫu"
                :value="templateSummary.total"
                :icon="Calendar"
                color="indigo"
            />
            <StatCard
                label="Mẫu đang bật"
                :value="templateSummary.active"
                :icon="CheckCircle2"
                color="emerald"
            />
            <StatCard
                label="Đợt mã đã tạo"
                :value="templateSummary.batches"
                :icon="Zap"
                color="amber"
            />
            <StatCard
                label="Mã giảm giá đã tạo"
                :value="templateSummary.codes"
                :icon="Tag"
                color="violet"
            />
        </div>

        <!-- AI Advisor Banner -->
        <SectionCard
            accent-color="violet"
            class="!space-y-0 border-border/60 bg-card/60 [background-image:none]"
        >
            <div
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-start gap-3.5">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary"
                    >
                        <Sparkles class="size-5 animate-pulse" />
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-bold text-foreground">
                            Trợ lý AI & gợi ý chiến dịch:
                            {{ aiRecommendation.title }}
                        </h4>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            {{ aiRecommendation.text }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex shrink-0 items-center gap-2 self-end md:self-center"
                >
                    <Button
                        size="sm"
                        class="bg-primary text-xs font-bold text-primary-foreground hover:bg-primary/90"
                        @click="applyAiRecommendation"
                    >
                        <Zap class="mr-1.5 size-3.5" /> Áp dụng ngay
                    </Button>
                </div>
            </div>
        </SectionCard>

        <!-- Template Gallery -->
        <div
            v-if="templates.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="t in templates"
                :key="t.id"
                :class="[
                    'group relative overflow-hidden rounded-2xl border p-5 transition-all duration-300 hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md',
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
                        {{ t.is_active ? 'Đang bật' : 'Đã tắt' }}
                    </StatusBadge>
                </div>
                <h3 class="mt-3 text-base font-bold text-foreground">
                    {{ t.name }}
                </h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ seasons[t.season] ?? t.season }}
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span
                        class="rounded-md bg-card/80 px-2 py-1 font-mono text-xs font-bold text-primary"
                    >
                        {{
                            t.discount_type === 'percent'
                                ? `${Math.round(t.discount_value)}%`
                                : `${Math.round(t.discount_value).toLocaleString('vi')}₫`
                        }}
                    </span>
                    <span
                        class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
                        >{{ t.default_duration_days }} ngày</span
                    >
                    <span
                        class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground"
                        >{{ t.code_prefix }}-XXX</span
                    >
                </div>

                <!-- Budget & Usage Stats -->
                <div
                    class="mt-4 grid grid-cols-2 gap-2 border-t border-border/20 pt-3 text-[11px] font-semibold text-muted-foreground"
                >
                    <div>
                        <p
                            class="text-[9px] tracking-wider text-muted-foreground/80 uppercase"
                        >
                            Batch đã tạo
                        </p>
                        <p class="font-mono text-xs font-bold text-foreground">
                            {{ t.batches_count }} đợt
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[9px] tracking-wider text-muted-foreground/80 uppercase"
                        >
                            Mã / Lượt dùng
                        </p>
                        <p class="font-mono text-xs font-bold text-foreground">
                            {{ t.total_codes }} mã / {{ t.total_usages }} lượt
                        </p>
                    </div>
                </div>

                <p
                    v-if="t.description"
                    class="mt-2 line-clamp-2 text-xs text-muted-foreground"
                >
                    {{ t.description }}
                </p>

                <div
                    class="mt-4 flex items-center justify-end gap-1 border-t border-border/30 pt-3"
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
            description="Tạo mẫu cho các dịp lễ như Tết, Valentine, Black Friday để sinh mã ưu đãi hàng loạt."
        >
            <template #action>
                <div class="flex items-center gap-3">
                    <Button
                        @click="seedDefaults"
                        variant="outline"
                        class="gap-1.5 border-amber-500/30 text-amber-500 hover:bg-amber-500/10"
                    >
                        <Sparkles class="size-4 animate-pulse text-amber-500" />
                        Khởi tạo bản mẫu gợi ý
                    </Button>
                    <Button @click="showCreate = true" class="gap-1.5">
                        <Plus class="size-4" /> Tạo mẫu
                    </Button>
                </div>
            </template>
        </EmptyState>
    </div>

    <!-- Create Dialog -->
    <Dialog v-model:open="showCreate">
        <DialogContent class="rounded-2xl p-8 sm:max-w-4xl">
            <DialogHeader class="border-b border-border/40 pb-4">
                <DialogTitle
                    class="flex items-center gap-2 text-base font-bold"
                >
                    <div
                        class="flex size-7 items-center justify-center rounded-lg border border-indigo-500/20 bg-indigo-500/10 text-indigo-500"
                    >
                        <Calendar class="size-4" />
                    </div>
                    <span>Tạo mẫu chiến dịch mới</span>
                </DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="py-2">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-5">
                    <!-- Left: Form inputs -->
                    <div class="space-y-5 md:col-span-3">
                        <!-- Name -->
                        <div class="grid gap-1.5">
                            <Label
                                class="text-[10px] font-black tracking-wider text-muted-foreground/80 uppercase"
                                >Tên chiến dịch *</Label
                            >
                            <div class="relative flex items-center">
                                <Gift
                                    class="absolute left-3 size-4 text-muted-foreground"
                                />
                                <Input
                                    v-model="form.name"
                                    placeholder="Khuyến mãi Tết 2027"
                                    required
                                    class="rounded-xl pl-9 focus-visible:border-primary focus-visible:ring-primary/20"
                                />
                            </div>
                        </div>

                        <!-- Slug & Season -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-[10px] font-black tracking-wider text-muted-foreground/80 uppercase"
                                    >Slug *</Label
                                >
                                <div class="relative flex items-center">
                                    <LinkIcon
                                        class="absolute left-3 size-4 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="form.slug"
                                        placeholder="tet-2027"
                                        required
                                        class="rounded-xl pl-9 font-mono text-xs focus-visible:border-primary focus-visible:ring-primary/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-[10px] font-black tracking-wider text-muted-foreground/80 uppercase"
                                    >Mùa/Dịp *</Label
                                >
                                <Select v-model="form.season">
                                    <SelectTrigger
                                        class="h-9 rounded-xl focus:border-primary focus:ring-primary/20"
                                    >
                                        <SelectValue placeholder="Chọn..." />
                                    </SelectTrigger>
                                    <SelectContent class="rounded-xl">
                                        <SelectItem
                                            v-for="(label, key) in seasons"
                                            :key="key"
                                            :value="key"
                                            >{{ label }}</SelectItem
                                        >
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <!-- Discount Type & Value -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-[10px] font-black tracking-wider text-muted-foreground/80 uppercase"
                                    >Loại giảm giá</Label
                                >
                                <Select v-model="form.discount_type">
                                    <SelectTrigger
                                        class="h-9 rounded-xl focus:border-primary focus:ring-primary/20"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent class="rounded-xl">
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
                                <Label
                                    class="text-[10px] font-black tracking-wider text-muted-foreground/80 uppercase"
                                    >Giá trị giảm</Label
                                >
                                <div class="relative flex items-center">
                                    <component
                                        :is="
                                            form.discount_type === 'percent'
                                                ? Percent
                                                : DollarSign
                                        "
                                        class="absolute left-3 size-4 text-muted-foreground"
                                    />
                                    <Input
                                        v-model.number="form.discount_value"
                                        type="number"
                                        min="0"
                                        required
                                        class="rounded-xl pl-9 focus-visible:border-primary focus-visible:ring-primary/20"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Prefix & Duration -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-[10px] font-black tracking-wider text-muted-foreground/80 uppercase"
                                    >Tiền tố mã *</Label
                                >
                                <div class="relative flex items-center">
                                    <Tag
                                        class="absolute left-3 size-4 text-muted-foreground"
                                    />
                                    <Input
                                        v-model="form.code_prefix"
                                        placeholder="TET"
                                        maxlength="10"
                                        required
                                        class="rounded-xl pl-9 font-mono font-bold tracking-wider uppercase focus-visible:border-primary focus-visible:ring-primary/20"
                                    />
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label
                                    class="text-[10px] font-black tracking-wider text-muted-foreground/80 uppercase"
                                    >Thời hạn (ngày)</Label
                                >
                                <div class="relative flex items-center">
                                    <Clock
                                        class="absolute left-3 size-4 text-muted-foreground"
                                    />
                                    <Input
                                        v-model.number="
                                            form.default_duration_days
                                        "
                                        type="number"
                                        min="1"
                                        required
                                        class="rounded-xl pl-9 focus-visible:border-primary focus-visible:ring-primary/20"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="grid gap-1.5">
                            <Label
                                class="text-[10px] font-black tracking-wider text-muted-foreground/80 uppercase"
                                >Mô tả</Label
                            >
                            <div class="relative flex items-center">
                                <FileText
                                    class="absolute left-3 size-4 text-muted-foreground"
                                />
                                <Input
                                    v-model="form.description"
                                    placeholder="Mô tả chiến dịch..."
                                    class="rounded-xl pl-9 focus-visible:border-primary focus-visible:ring-primary/20"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Right: Live Preview -->
                    <div
                        class="flex flex-col justify-start border-t border-border/40 pt-4 md:col-span-2 md:border-t-0 md:border-l md:pt-0 md:pl-8"
                    >
                        <p
                            class="mb-3 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >
                            Xem trước hiển thị
                        </p>

                        <div
                            :class="[
                                'group relative w-full overflow-hidden rounded-2xl border border-border/60 bg-card/80 p-5 shadow-xs transition-all duration-300',
                                seasonColors[form.season] ??
                                    seasonColors.custom,
                            ]"
                        >
                            <div class="flex items-start justify-between">
                                <div class="text-3xl">
                                    {{ seasonEmojis[form.season] ?? '⚡' }}
                                </div>
                                <StatusBadge status="active" size="sm">
                                    Đang bật
                                </StatusBadge>
                            </div>
                            <h3
                                class="mt-3 truncate text-base font-bold text-foreground"
                            >
                                {{ form.name || 'Tên chiến dịch' }}
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ seasons[form.season] || 'Tùy chỉnh' }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-md bg-card/80 px-2 py-1 font-mono text-xs font-bold text-primary"
                                >
                                    {{
                                        form.discount_type === 'percent'
                                            ? `${form.discount_value || 0}%`
                                            : `${(form.discount_value || 0).toLocaleString('vi')}₫`
                                    }}
                                </span>
                                <span
                                    class="rounded bg-muted px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                    >{{
                                        form.default_duration_days || 0
                                    }}
                                    ngày</span
                                >
                                <span
                                    class="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] font-semibold text-muted-foreground"
                                    >{{
                                        (
                                            form.code_prefix || 'PREFIX'
                                        ).toUpperCase()
                                    }}-XXX</span
                                >
                            </div>

                            <!-- Dummy performance stats for preview -->
                            <div
                                class="mt-4 grid grid-cols-2 gap-2 border-t border-border/20 pt-3 text-[11px] font-semibold text-muted-foreground"
                            >
                                <div>
                                    <p
                                        class="text-[9px] tracking-wider text-muted-foreground/80 uppercase"
                                    >
                                        Batch đã tạo
                                    </p>
                                    <p
                                        class="font-mono text-xs font-bold text-foreground"
                                    >
                                        0 đợt
                                    </p>
                                </div>
                                <div>
                                    <p
                                        class="text-[9px] tracking-wider text-muted-foreground/80 uppercase"
                                    >
                                        Mã / Lượt dùng
                                    </p>
                                    <p
                                        class="font-mono text-xs font-bold text-foreground"
                                    >
                                        0 mã / 0 lượt
                                    </p>
                                </div>
                            </div>

                            <p
                                v-if="form.description"
                                class="mt-2 line-clamp-2 text-xs text-muted-foreground"
                            >
                                {{ form.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter class="mt-6 border-t border-border/40 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showCreate = false"
                        class="rounded-xl font-bold"
                        >Hủy</Button
                    >
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl font-bold"
                        >Tạo mẫu</Button
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
