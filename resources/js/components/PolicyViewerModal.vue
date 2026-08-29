<script setup lang="ts">
import axios from 'axios';
import {
    AlertCircle,
    BookOpen,
    ChevronRight,
    FileText,
    Inbox,
    RefreshCw,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits(['update:isOpen']);

const isLoading = ref(false);
const hasError = ref(false);
const searchQuery = ref('');
const selectedCategory = ref<string>('all');
const policies = ref<Array<any>>([]);
const categories = ref<Array<{ code: string; name: string }>>([]);
const selectedPolicy = ref<any>(null);

const sidebarSummary = computed(() => {
    if (policies.value.length === 0) {
        return `${categories.value.length} danh mục sẵn sàng`;
    }

    return `${filteredPolicies.value.length} quy định phù hợp`;
});

const filteredPolicies = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return policies.value.filter((policy) => {
        const matchesCategory =
            selectedCategory.value === 'all' ||
            policy.category === selectedCategory.value;
        const searchableText = [
            policy.title,
            policy.policy_code,
            policy.content,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return matchesCategory && (!query || searchableText.includes(query));
    });
});

const selectedCategoryLabel = computed(() => {
    if (selectedCategory.value === 'all') {
        return 'Tất cả danh mục';
    }

    return getCategoryLabel(selectedCategory.value);
});

const fetchPolicies = async () => {
    isLoading.value = true;
    hasError.value = false;

    try {
        const res = await axios.get('/api/company-policies');

        if (!res.data.success) {
            throw new Error('Unable to load policies');
        }

        policies.value = res.data.data || [];
        categories.value = res.data.categories || [];
        selectedPolicy.value = filteredPolicies.value[0] ?? null;
    } catch {
        policies.value = [];
        categories.value = [];
        selectedPolicy.value = null;
        hasError.value = true;
        toast.error('Không thể tải Bộ quy định tiêu chuẩn.');
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            searchQuery.value = '';
            selectedCategory.value = 'all';
            fetchPolicies();
        }
    },
);

watch([searchQuery, selectedCategory], () => {
    if (
        !selectedPolicy.value ||
        !filteredPolicies.value.some(
            (policy) => policy.id === selectedPolicy.value.id,
        )
    ) {
        selectedPolicy.value = filteredPolicies.value[0] ?? null;
    }
});

function getCategoryLabel(categoryCode: string) {
    return (
        categories.value.find((category) => category.code === categoryCode)
            ?.name ?? 'Quy Định Chung'
    );
}

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount || 0);
};

const closeModal = () => {
    emit('update:isOpen', false);
};

const clearFilters = () => {
    searchQuery.value = '';
    selectedCategory.value = 'all';
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/80 p-3 backdrop-blur-md sm:p-6"
        >
            <section
                class="flex h-[calc(100vh-1.5rem)] max-h-[820px] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-border bg-card text-card-foreground shadow-2xl sm:h-[calc(100vh-3rem)]"
                role="dialog"
                aria-modal="true"
                aria-label="Bộ Quy Định và Tiêu Chuẩn Vận Hành"
            >
                <header
                    class="flex shrink-0 items-center justify-between gap-4 border-b border-border bg-gradient-to-r from-indigo-500/15 via-background to-background px-4 py-4 sm:px-6"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="shrink-0 rounded-2xl border border-indigo-400/30 bg-indigo-500/15 p-2.5"
                        >
                            <BookOpen
                                class="size-6 text-indigo-500 dark:text-indigo-300"
                            />
                        </div>
                        <div class="min-w-0">
                            <p
                                class="mb-0.5 text-[10px] font-bold tracking-[0.2em] text-indigo-600 uppercase dark:text-indigo-300"
                            >
                                Trung tâm vận hành
                            </p>
                            <h3
                                class="truncate text-base font-bold text-foreground sm:text-lg"
                            >
                                Bộ Quy Định &amp; Tiêu Chuẩn
                            </h3>
                            <p
                                class="hidden truncate text-xs text-muted-foreground sm:block"
                            >
                                Tra cứu các quy tắc, tiêu chuẩn và mức phạt đang
                                áp dụng.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        aria-label="Đóng bộ quy định"
                        class="shrink-0 rounded-xl p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        @click="closeModal"
                    >
                        <X class="size-5" />
                    </button>
                </header>

                <div class="flex min-h-0 flex-1 flex-col md:flex-row">
                    <aside
                        class="flex min-h-0 w-full shrink-0 flex-col border-b border-border bg-muted/20 md:w-[340px] md:border-r md:border-b-0"
                    >
                        <div
                            class="shrink-0 space-y-3 border-b border-border p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="text-xs font-bold text-foreground"
                                    >
                                        Danh sách quy định
                                    </p>
                                    <p
                                        class="mt-0.5 text-[11px] text-muted-foreground"
                                    >
                                        {{ sidebarSummary }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full border border-indigo-500/25 bg-indigo-500/10 px-2 py-1 text-[10px] font-semibold text-indigo-600 dark:text-indigo-300"
                                >
                                    {{ selectedCategoryLabel }}
                                </span>
                            </div>

                            <div class="relative">
                                <Search
                                    class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                                />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Tìm theo mã hoặc tên..."
                                    class="h-9 border-border bg-background/70 pl-9 text-xs text-foreground placeholder:text-muted-foreground"
                                />
                            </div>

                            <div
                                class="flex flex-wrap content-start gap-1.5 pr-1"
                            >
                                <button
                                    type="button"
                                    class="rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold transition"
                                    :class="
                                        selectedCategory === 'all'
                                            ? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
                                            : 'border-border bg-background/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                                    "
                                    @click="selectedCategory = 'all'"
                                >
                                    Tất cả
                                </button>
                                <button
                                    v-for="category in categories"
                                    :key="category.code"
                                    type="button"
                                    class="max-w-full truncate rounded-lg border px-2.5 py-1.5 text-[11px] font-semibold transition"
                                    :class="
                                        selectedCategory === category.code
                                            ? 'border-indigo-500 bg-indigo-600 text-white shadow-sm'
                                            : 'border-border bg-background/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                                    "
                                    :title="category.name"
                                    @click="selectedCategory = category.code"
                                >
                                    {{ category.name }}
                                </button>
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto p-3">
                            <div
                                v-if="isLoading"
                                class="flex h-full min-h-40 flex-col items-center justify-center gap-3 text-center"
                            >
                                <RefreshCw
                                    class="size-5 animate-spin text-indigo-500"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Đang tải quy định...
                                </p>
                            </div>

                            <div
                                v-else-if="hasError"
                                class="flex h-full min-h-40 flex-col items-center justify-center gap-3 px-4 text-center"
                            >
                                <div
                                    class="rounded-2xl border border-rose-500/20 bg-rose-500/10 p-3"
                                >
                                    <AlertCircle class="size-5 text-rose-500" />
                                </div>
                                <div>
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Không tải được dữ liệu
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-muted-foreground"
                                    >
                                        Vui lòng thử lại sau giây lát.
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="h-8 text-xs"
                                    @click="fetchPolicies"
                                >
                                    <RefreshCw class="mr-1.5 size-3.5" /> Thử
                                    lại
                                </Button>
                            </div>

                            <div
                                v-else-if="policies.length === 0"
                                class="rounded-2xl border border-dashed border-border bg-background/40 p-6 text-center"
                            >
                                <div
                                    class="mx-auto w-fit rounded-2xl border border-border bg-background/70 p-3"
                                >
                                    <Inbox
                                        class="size-5 text-muted-foreground"
                                    />
                                </div>
                                <div class="mt-3">
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Chưa có quy định được ban hành
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-muted-foreground"
                                    >
                                        Các danh mục phía trên đã sẵn sàng. Nội
                                        dung sẽ xuất hiện sau khi quản trị viên
                                        tạo quy định.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else-if="filteredPolicies.length === 0"
                                class="rounded-2xl border border-dashed border-border bg-background/40 p-6 text-center"
                            >
                                <div
                                    class="mx-auto w-fit rounded-2xl border border-border bg-background/70 p-3"
                                >
                                    <Search
                                        class="size-5 text-muted-foreground"
                                    />
                                </div>
                                <div class="mt-3">
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Không có quy định phù hợp
                                    </p>
                                    <p
                                        class="mt-1 text-xs leading-5 text-muted-foreground"
                                    >
                                        Bộ lọc hiện tại không tìm thấy quy định
                                        nào.
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="mt-4 h-8 text-xs"
                                    @click="clearFilters"
                                >
                                    Xóa bộ lọc
                                </Button>
                            </div>

                            <div v-else class="space-y-2">
                                <button
                                    v-for="policy in filteredPolicies"
                                    :key="policy.id"
                                    type="button"
                                    class="group w-full rounded-2xl border p-3 text-left transition"
                                    :class="
                                        selectedPolicy?.id === policy.id
                                            ? 'border-indigo-500/60 bg-indigo-500/10 shadow-sm'
                                            : 'border-border bg-background/40 hover:border-indigo-500/35 hover:bg-background/80'
                                    "
                                    @click="selectedPolicy = policy"
                                >
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="font-mono text-[10px] font-bold tracking-wide text-indigo-600 dark:text-indigo-300"
                                        >
                                            {{ policy.policy_code }}
                                        </span>
                                        <ChevronRight
                                            class="size-3.5 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5"
                                        />
                                    </div>
                                    <h4
                                        class="mt-2 line-clamp-2 text-sm leading-5 font-bold text-foreground"
                                    >
                                        {{ policy.title }}
                                    </h4>
                                    <span
                                        class="mt-2 inline-flex max-w-full truncate rounded-md bg-indigo-500/10 px-2 py-1 text-[10px] font-semibold text-indigo-600 dark:text-indigo-300"
                                    >
                                        {{ getCategoryLabel(policy.category) }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </aside>

                    <main class="flex min-h-0 flex-1 flex-col bg-background/50">
                        <div
                            v-if="selectedPolicy"
                            class="min-h-0 flex-1 overflow-y-auto p-5 sm:p-8"
                        >
                            <div class="mx-auto max-w-3xl space-y-6">
                                <div class="border-b border-border pb-5">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="rounded-md bg-indigo-500/10 px-2 py-1 font-mono text-xs font-bold text-indigo-600 dark:text-indigo-300"
                                        >
                                            {{ selectedPolicy.policy_code }}
                                        </span>
                                        <span
                                            class="rounded-md border border-border bg-muted/50 px-2 py-1 text-[11px] font-semibold text-muted-foreground"
                                        >
                                            {{
                                                getCategoryLabel(
                                                    selectedPolicy.category,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <h2
                                        class="mt-4 text-2xl leading-tight font-bold text-foreground sm:text-3xl"
                                    >
                                        {{ selectedPolicy.title }}
                                    </h2>
                                    <p
                                        class="mt-3 text-sm leading-6 text-muted-foreground"
                                    >
                                        Quy định này đang được áp dụng cho
                                        <strong class="text-foreground">
                                            {{
                                                selectedPolicy.applies_to_all_branches
                                                    ? 'tất cả chi nhánh toàn chuỗi'
                                                    : 'chi nhánh được chỉ định'
                                            }} </strong
                                        >.
                                    </p>
                                </div>

                                <section
                                    class="rounded-2xl border border-border bg-card/70 p-5"
                                >
                                    <p
                                        class="mb-3 text-[10px] font-bold tracking-[0.18em] text-muted-foreground uppercase"
                                    >
                                        Nội dung quy định
                                    </p>
                                    <div
                                        class="text-sm leading-7 whitespace-pre-line text-foreground/90"
                                    >
                                        {{ selectedPolicy.content }}
                                    </div>
                                </section>

                                <div
                                    v-if="
                                        selectedPolicy.suggested_fine_amount > 0
                                    "
                                    class="flex flex-col gap-2 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm text-rose-700 sm:flex-row sm:items-center sm:justify-between dark:text-rose-200"
                                >
                                    <span class="font-semibold"
                                        >Mức phạt tham chiếu</span
                                    >
                                    <span
                                        class="font-mono font-bold text-rose-600 dark:text-rose-200"
                                    >
                                        {{
                                            formatCurrency(
                                                selectedPolicy.suggested_fine_amount,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else-if="hasError"
                            class="flex min-h-0 flex-1 flex-col items-center justify-center px-6 text-center"
                        >
                            <div
                                class="rounded-3xl border border-rose-500/20 bg-rose-500/10 p-5"
                            >
                                <AlertCircle class="size-10 text-rose-500" />
                            </div>
                            <p
                                class="mt-5 text-base font-semibold text-foreground"
                            >
                                Không thể hiển thị bộ quy định
                            </p>
                            <p
                                class="mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
                            >
                                Hãy thử tải lại dữ liệu để tiếp tục.
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                class="mt-4 h-9 text-xs"
                                @click="fetchPolicies"
                            >
                                <RefreshCw class="mr-1.5 size-3.5" /> Thử lại
                            </Button>
                        </div>

                        <div
                            v-else-if="policies.length === 0"
                            class="flex min-h-0 flex-1 flex-col items-center justify-center px-6 text-center"
                        >
                            <div
                                class="rounded-3xl border border-indigo-500/20 bg-indigo-500/10 p-5"
                            >
                                <FileText
                                    class="size-10 text-indigo-500 dark:text-indigo-300"
                                />
                            </div>
                            <p
                                class="mt-5 text-base font-semibold text-foreground"
                            >
                                Chưa có quy định được ban hành
                            </p>
                            <p
                                class="mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
                            >
                                Khu vực này sẽ hiển thị nội dung chi tiết sau
                                khi quản trị viên ban hành quy định.
                            </p>
                        </div>

                        <div
                            v-else
                            class="flex min-h-0 flex-1 flex-col items-center justify-center px-6 text-center"
                        >
                            <div
                                class="rounded-3xl border border-border bg-muted/30 p-5"
                            >
                                <Search class="size-10 text-muted-foreground" />
                            </div>
                            <p
                                class="mt-5 text-base font-semibold text-foreground"
                            >
                                Không có quy định phù hợp
                            </p>
                            <p
                                class="mt-2 max-w-sm text-sm leading-6 text-muted-foreground"
                            >
                                Hãy thử bỏ bộ lọc để xem lại toàn bộ danh sách.
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                class="mt-4 h-9 text-xs"
                                @click="clearFilters"
                            >
                                Xem tất cả quy định
                            </Button>
                        </div>

                        <footer
                            class="flex shrink-0 items-center justify-between gap-3 border-t border-border px-5 py-3 sm:px-8"
                        >
                            <span
                                class="hidden text-xs text-muted-foreground sm:block"
                            >
                                Chỉ hiển thị quy định đang được ban hành.
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-9 px-4 text-xs"
                                @click="closeModal"
                            >
                                Đóng
                            </Button>
                        </footer>
                    </main>
                </div>
            </section>
        </div>
    </Teleport>
</template>
