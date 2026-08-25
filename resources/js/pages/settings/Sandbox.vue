<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Coffee,
    Flame,
    FlaskConical,
    Loader2,
    RefreshCcw,
    Sparkles,
    ToggleLeft,
    ToggleRight,
    Trash2,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

// ─── Props ──────────────────────────────────────────────────────────────────
type SandboxData = {
    sandbox_mode: boolean;
    sandbox_template: string | null;
    sandbox_seeded_at: string | null;
};

const props = defineProps<{
    sandbox: SandboxData | null;
    status?: string | null;
    error?: string | null;
}>();

// ─── Layout breadcrumbs ──────────────────────────────────────────────────────
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Sandbox & Demo Data', href: '/settings/sandbox' },
        ],
    },
});

// ─── State ───────────────────────────────────────────────────────────────────
const loadingToggle = ref(false);
const loadingTemplate = ref<string | null>(null);
const loadingReset = ref(false);
const showResetConfirm = ref(false);

const isSeeded = computed(() => !!props.sandbox?.sandbox_seeded_at);

// ─── Template definitions ────────────────────────────────────────────────────
const templates = [
    {
        id: 'bbq',
        icon: Flame,
        emoji: '🔥',
        name: 'Nhà hàng Nướng',
        tagline: 'Không khí ấm cúng, hương thịt nướng thơm lừng',
        color: 'from-orange-500 to-rose-600',
        bgLight: 'from-orange-50 to-rose-50',
        border: 'border-orange-200',
        badgeColor: 'bg-orange-100 text-orange-700',
        items: [
            '12 món nướng & combo',
            '2 khu vực, 9 bàn',
            '5 nhân viên mẫu',
            '3 ca làm việc',
            '100 đơn hàng 30 ngày qua',
            'Báo cáo doanh thu đầy đủ',
        ],
        avgPrice: '89K – 449K',
    },
    {
        id: 'cafe',
        icon: Coffee,
        emoji: '☕',
        name: 'Quán Cafe',
        tagline: 'Không gian yên tĩnh, hương cà phê dịu nhẹ',
        color: 'from-amber-600 to-yellow-700',
        bgLight: 'from-amber-50 to-yellow-50',
        border: 'border-amber-200',
        badgeColor: 'bg-amber-100 text-amber-700',
        items: [
            '15 món cà phê & bánh ngọt',
            '2 khu vực, 9 bàn',
            '5 nhân viên mẫu',
            '3 ca làm việc',
            '100 đơn hàng 30 ngày qua',
            'Biểu đồ doanh thu theo ngày',
        ],
        avgPrice: '35K – 95K',
    },
    {
        id: 'bubble_tea',
        icon: Zap,
        emoji: '🧋',
        name: 'Tiệm Trà Sữa',
        tagline: 'Trẻ trung, năng động, đa dạng topping',
        color: 'from-purple-500 to-pink-600',
        bgLight: 'from-purple-50 to-pink-50',
        border: 'border-purple-200',
        badgeColor: 'bg-purple-100 text-purple-700',
        items: [
            '18 món trà sữa & snack',
            '1 khu vực, 8 bàn',
            '5 nhân viên mẫu',
            '3 ca làm việc',
            '100 đơn hàng 30 ngày qua',
            'Phân tích sản phẩm bán chạy',
        ],
        avgPrice: '25K – 149K',
    },
];

const currentTemplate = computed(
    () =>
        templates.find((t) => t.id === props.sandbox?.sandbox_template) ?? null,
);

function formatDate(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

// ─── Actions ─────────────────────────────────────────────────────────────────
function toggleSandbox() {
    if (loadingToggle.value) {
        return;
    }

    loadingToggle.value = true;
    router.post(
        '/settings/sandbox/toggle',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                loadingToggle.value = false;
            },
        },
    );
}

function seedTemplate(templateId: string) {
    if (loadingTemplate.value) {
        return;
    }

    loadingTemplate.value = templateId;
    router.post(
        '/settings/sandbox/seed',
        { template: templateId },
        {
            preserveScroll: true,
            onFinish: () => {
                loadingTemplate.value = null;
            },
        },
    );
}

function resetDemo() {
    if (loadingReset.value) {
        return;
    }

    loadingReset.value = true;
    showResetConfirm.value = false;
    router.post(
        '/settings/sandbox/reset',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                loadingReset.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Sandbox & Demo Data · Aventura" />

    <div class="w-full space-y-8">
        <!-- Header -->
        <div class="settings-page-intro flex items-start gap-4">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 shadow-lg shadow-violet-200 dark:shadow-violet-900/30"
            >
                <FlaskConical class="size-6 text-white" />
            </div>
            <div>
                <h1 class="text-2xl font-bold text-foreground">
                    Sandbox & Demo Data
                </h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Khám phá toàn bộ tính năng với dữ liệu mẫu thực tế. Nạp —
                    trải nghiệm — xóa chỉ với 1 click.
                </p>
            </div>
        </div>

        <!-- Flash messages -->
        <div
            v-if="props.status"
            class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
        >
            <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-emerald-500" />
            <span>{{ props.status }}</span>
        </div>
        <div
            v-if="props.error"
            class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0 text-rose-500" />
            <span>{{ props.error }}</span>
        </div>

        <!-- Sandbox Mode Toggle -->
        <div class="settings-surface p-6 lg:p-8">
            <div class="flex items-center justify-between gap-6">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-semibold text-foreground">
                            Chế độ Thử nghiệm
                        </h2>
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="
                                sandbox?.sandbox_mode
                                    ? 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/30'
                                    : 'bg-muted text-muted-foreground ring-border'
                            "
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="
                                    sandbox?.sandbox_mode
                                        ? 'bg-violet-500'
                                        : 'bg-muted-foreground/50'
                                "
                            />
                            {{
                                sandbox?.sandbox_mode ? 'Đang bật' : 'Đang tắt'
                            }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Khi bật, toàn bộ dữ liệu trong hệ thống được đánh dấu là
                        "dữ liệu thử nghiệm" — không ảnh hưởng dữ liệu thực tế.
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="loadingToggle"
                    class="relative shrink-0 rounded-full transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    :class="
                        loadingToggle
                            ? 'cursor-not-allowed opacity-60'
                            : 'cursor-pointer'
                    "
                    @click="toggleSandbox"
                >
                    <Loader2
                        v-if="loadingToggle"
                        class="size-10 animate-spin text-muted-foreground"
                    />
                    <ToggleRight
                        v-else-if="sandbox?.sandbox_mode"
                        class="size-12 text-violet-600 transition-transform hover:scale-110 dark:text-violet-400"
                    />
                    <ToggleLeft
                        v-else
                        class="size-12 text-muted-foreground/50 transition-transform hover:scale-110 hover:text-muted-foreground"
                    />
                </button>
            </div>
        </div>

        <!-- Seeded Status Banner (khi đã có dữ liệu mẫu) -->
        <div
            v-if="isSeeded && currentTemplate"
            class="overflow-hidden rounded-2xl border"
            :class="currentTemplate.border"
        >
            <div
                class="bg-gradient-to-r p-5"
                :class="
                    currentTemplate.bgLight +
                    ' dark:from-muted/30 dark:to-muted/10'
                "
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="text-3xl">{{
                            currentTemplate.emoji
                        }}</span>
                        <div>
                            <p
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >
                                Đang chạy template
                            </p>
                            <h3 class="text-base font-bold text-foreground">
                                {{ currentTemplate.name }}
                            </h3>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Nạp lúc
                                {{
                                    formatDate(
                                        sandbox?.sandbox_seeded_at ?? null,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span
                            class="flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"
                        >
                            <span
                                class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                            />
                            Dữ liệu đang hoạt động
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        href="/dashboard"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/80 px-3 py-1.5 text-xs font-medium text-foreground shadow-sm ring-1 ring-border transition-colors hover:bg-white dark:bg-muted/50 dark:hover:bg-muted"
                    >
                        <Sparkles class="size-3.5 text-amber-500" />
                        Xem Dashboard
                    </a>
                    <a
                        href="/orders/create"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/80 px-3 py-1.5 text-xs font-medium text-foreground shadow-sm ring-1 ring-border transition-colors hover:bg-white dark:bg-muted/50 dark:hover:bg-muted"
                    >
                        <Zap class="size-3.5 text-blue-500" />
                        Thử POS
                    </a>
                    <a
                        href="/kitchen"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/80 px-3 py-1.5 text-xs font-medium text-foreground shadow-sm ring-1 ring-border transition-colors hover:bg-white dark:bg-muted/50 dark:hover:bg-muted"
                    >
                        <Flame class="size-3.5 text-rose-500" />
                        Màn hình Bếp
                    </a>
                    <a
                        href="/reports"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white/80 px-3 py-1.5 text-xs font-medium text-foreground shadow-sm ring-1 ring-border transition-colors hover:bg-white dark:bg-muted/50 dark:hover:bg-muted"
                    >
                        <CheckCircle2 class="size-3.5 text-emerald-500" />
                        Báo cáo
                    </a>
                </div>
            </div>
        </div>

        <!-- Template Cards -->
        <div v-if="!isSeeded" class="space-y-4">
            <div class="flex items-center gap-2">
                <Sparkles class="size-4 text-amber-500" />
                <h2
                    class="text-sm font-semibold tracking-wider text-foreground uppercase"
                >
                    Chọn template dữ liệu mẫu
                </h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="group relative cursor-default overflow-hidden rounded-2xl border bg-card shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                    :class="template.border"
                >
                    <!-- Gradient header -->
                    <div class="bg-gradient-to-br p-5" :class="template.color">
                        <div class="flex items-center justify-between">
                            <span class="text-4xl drop-shadow-sm">{{
                                template.emoji
                            }}</span>
                            <span
                                class="rounded-full bg-white/20 px-2.5 py-1 text-xs font-semibold text-white backdrop-blur-sm"
                            >
                                {{ template.avgPrice }}
                            </span>
                        </div>
                        <div class="mt-3">
                            <h3
                                class="text-base leading-tight font-bold text-white"
                            >
                                {{ template.name }}
                            </h3>
                            <p class="mt-0.5 text-xs text-white/80">
                                {{ template.tagline }}
                            </p>
                        </div>
                    </div>

                    <!-- Items list -->
                    <div class="space-y-2 p-4">
                        <p
                            class="mb-3 text-xs font-medium tracking-wider text-muted-foreground uppercase"
                        >
                            Dữ liệu sẽ được tạo
                        </p>
                        <ul class="space-y-1.5">
                            <li
                                v-for="item in template.items"
                                :key="item"
                                class="flex items-center gap-2 text-xs text-foreground"
                            >
                                <CheckCircle2
                                    class="size-3.5 shrink-0 text-emerald-500"
                                />
                                {{ item }}
                            </li>
                        </ul>
                    </div>

                    <!-- Action button -->
                    <div class="px-4 pb-4">
                        <Button
                            type="button"
                            class="w-full border-none bg-gradient-to-r font-medium text-white shadow-sm transition-all"
                            :class="template.color"
                            :disabled="!!loadingTemplate"
                            @click="seedTemplate(template.id)"
                        >
                            <Loader2
                                v-if="loadingTemplate === template.id"
                                class="mr-2 size-4 animate-spin"
                            />
                            <Sparkles v-else class="mr-2 size-4" />
                            {{
                                loadingTemplate === template.id
                                    ? 'Đang nạp dữ liệu...'
                                    : 'Nạp dữ liệu này'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Info when already seeded: can't load another template -->
        <div
            v-if="isSeeded"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
        >
            <div class="flex items-start gap-2">
                <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-500" />
                <div>
                    <p class="font-medium">Đã có dữ liệu mẫu</p>
                    <p class="mt-0.5 text-xs">
                        Để nạp template khác, bạn cần xóa dữ liệu hiện tại
                        trước. Nhấn nút bên dưới để xóa và bắt đầu lại.
                    </p>
                </div>
            </div>
        </div>

        <!-- Reset Section -->
        <div
            v-if="isSeeded"
            class="rounded-2xl border border-rose-200 bg-rose-50/50 p-6 dark:border-rose-500/20 dark:bg-rose-500/5"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-500/10"
                >
                    <Trash2 class="size-5 text-rose-600 dark:text-rose-400" />
                </div>
                <div class="min-w-0 flex-1">
                    <h3 class="font-semibold text-foreground">
                        Xóa dữ liệu mẫu
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Hành động này sẽ xóa <strong>toàn bộ</strong> dữ liệu
                        mẫu bao gồm: đơn hàng, nhân viên, sản phẩm, thực đơn,
                        bàn bè và lịch sử doanh thu. Không thể hoàn tác. Nhà
                        hàng sẽ trở về trạng thái trống để bắt đầu thực tế.
                    </p>

                    <!-- Confirm step -->
                    <div v-if="!showResetConfirm" class="mt-4">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-rose-300 text-rose-700 hover:bg-rose-100 hover:text-rose-800 dark:border-rose-500/40 dark:text-rose-400 dark:hover:bg-rose-500/10"
                            @click="showResetConfirm = true"
                        >
                            <RefreshCcw class="mr-2 size-4" />
                            Xóa dữ liệu mẫu & chuyển về Production
                        </Button>
                    </div>

                    <!-- Confirmation dialog inline -->
                    <div
                        v-else
                        class="mt-4 rounded-xl border border-rose-300 bg-rose-100 p-4 dark:border-rose-500/30 dark:bg-rose-500/10"
                    >
                        <p
                            class="text-sm font-medium text-rose-800 dark:text-rose-300"
                        >
                            ⚠️ Bạn có chắc chắn muốn xóa toàn bộ dữ liệu mẫu
                            không?
                        </p>
                        <p
                            class="mt-1 text-xs text-rose-700 dark:text-rose-400"
                        >
                            Hành động này <strong>không thể hoàn tác</strong>.
                            Tất cả đơn hàng, nhân viên và sản phẩm mẫu sẽ bị xóa
                            vĩnh viễn.
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <Button
                                type="button"
                                class="border-none bg-rose-600 text-white hover:bg-rose-700"
                                :disabled="loadingReset"
                                @click="resetDemo"
                            >
                                <Loader2
                                    v-if="loadingReset"
                                    class="mr-2 size-4 animate-spin"
                                />
                                <Trash2 v-else class="mr-2 size-4" />
                                {{
                                    loadingReset
                                        ? 'Đang xóa...'
                                        : 'Xác nhận xóa'
                                }}
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                :disabled="loadingReset"
                                @click="showResetConfirm = false"
                            >
                                Hủy
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- How it works -->
        <div class="rounded-2xl border border-border bg-muted/30 p-6">
            <h3
                class="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground"
            >
                <FlaskConical class="size-4 text-violet-500" />
                Cách hoạt động
            </h3>
            <ol class="space-y-3">
                <li class="flex gap-3 text-sm text-muted-foreground">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                        >1</span
                    >
                    <span
                        ><strong class="text-foreground"
                            >Bật Chế độ Thử nghiệm</strong
                        >
                        — Đánh dấu hệ thống là môi trường sandbox.</span
                    >
                </li>
                <li class="flex gap-3 text-sm text-muted-foreground">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                        >2</span
                    >
                    <span
                        ><strong class="text-foreground">Chọn template</strong>
                        — Hệ thống tự động tạo sản phẩm, nhân viên, ca làm việc
                        và 100 đơn hàng lịch sử.</span
                    >
                </li>
                <li class="flex gap-3 text-sm text-muted-foreground">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                        >3</span
                    >
                    <span
                        ><strong class="text-foreground">Khám phá tự do</strong>
                        — Dashboard, POS, Bếp, Báo cáo đều có dữ liệu thực tế để
                        trải nghiệm.</span
                    >
                </li>
                <li class="flex gap-3 text-sm text-muted-foreground">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"
                        >4</span
                    >
                    <span
                        ><strong class="text-foreground">Reset 1 click</strong>
                        — Sẵn sàng bắt đầu thực tế? Xóa sạch dữ liệu mẫu và nhập
                        dữ liệu thật của bạn.</span
                    >
                </li>
            </ol>
        </div>
    </div>
</template>
