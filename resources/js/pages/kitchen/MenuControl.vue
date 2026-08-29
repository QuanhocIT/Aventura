<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    ChevronLeft,
    Lock,
    Search,
    UtensilsCrossed,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

interface MenuProduct {
    id: number;
    name: string;
    price: number;
    category_id: number | null;
    category_name: string;
    image_url: string | null;
    is_available: boolean;
    is_unavailable: boolean;
    is_paused: boolean;
    out_of_stock_until: string | null;
    out_of_stock_reason: string | null;
}

interface MenuCategory {
    id: number;
    name: string;
}

const props = defineProps<{
    products: MenuProduct[];
    categories: MenuCategory[];
    branchName: string;
    today: string;
}>();

const step = ref<'select' | 'confirm'>('select');
const searchQuery = ref('');
const selectedCategoryId = ref<number | 'all'>('all');
const selectedIds = ref<number[]>([]);
const reason = ref('');
const isSubmitting = ref(false);

const filteredProducts = computed(() => {
    const search = searchQuery.value.trim().toLowerCase();

    return props.products.filter((product) => {
        const matchesCategory =
            selectedCategoryId.value === 'all' ||
            product.category_id === selectedCategoryId.value;
        const matchesSearch =
            !search || product.name.toLowerCase().includes(search);

        return matchesCategory && matchesSearch;
    });
});

const selectedProducts = computed(() =>
    props.products.filter((product) => selectedIds.value.includes(product.id)),
);

const numberFormat = (value: number) =>
    new Intl.NumberFormat('vi-VN').format(value);

const isDisabled = (product: MenuProduct) =>
    product.is_unavailable || product.is_paused || !product.is_available;

const toggleProduct = (product: MenuProduct) => {
    if (isDisabled(product)) {
        return;
    }

    if (selectedIds.value.includes(product.id)) {
        selectedIds.value = selectedIds.value.filter((id) => id !== product.id);
    } else {
        selectedIds.value = [...selectedIds.value, product.id];
    }
};

const goToConfirmation = () => {
    if (selectedIds.value.length === 0) {
        toast.error('Vui lòng chọn ít nhất một món cần tạm ngưng.');

        return;
    }

    step.value = 'confirm';
};

const goBackToSelection = () => {
    step.value = 'select';
};

const activateUnavailableProducts = () => {
    if (reason.value.trim().length < 3) {
        toast.error('Vui lòng ghi rõ lý do tạm ngưng món.');

        return;
    }

    isSubmitting.value = true;
    router.post(
        '/kitchen/menu-control/activate',
        {
            product_ids: selectedIds.value,
            reason: reason.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    `Đã tạm ngưng ${selectedIds.value.length} món đến hết ngày hôm nay.`,
                );
                selectedIds.value = [];
                reason.value = '';
                step.value = 'select';
            },
            onError: (errors: Record<string, string | string[]>) => {
                const message = Object.values(errors)[0];
                toast.error(
                    Array.isArray(message)
                        ? message[0]
                        : message || 'Không thể tạm ngưng các món đã chọn.',
                );
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Quản lý món bếp" />

    <div
        class="min-h-full bg-slate-50/60 px-4 py-6 sm:px-6 lg:px-8 dark:bg-slate-950"
    >
        <div class="mx-auto max-w-7xl space-y-6">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-12 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                    >
                        <UtensilsCrossed class="size-6" />
                    </div>
                    <div>
                        <p
                            class="text-xs font-bold tracking-wide text-indigo-600 uppercase dark:text-indigo-400"
                        >
                            {{ branchName }}
                        </p>
                        <h1
                            class="text-2xl font-black text-slate-900 dark:text-white"
                        >
                            Quản lý món phục vụ
                        </h1>
                        <p
                            class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                        >
                            Chọn các món bếp tạm thời không thể phục vụ để POS
                            và QR không lên nhầm đơn.
                        </p>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4 shrink-0" />
                        <span
                            >Trạng thái kích hoạt có hiệu lực đến hết ngày hôm
                            nay.</span
                        >
                    </div>
                </div>
            </div>

            <div v-if="step === 'select'" class="space-y-5">
                <div
                    class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="relative min-w-0 flex-1">
                            <Search
                                class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
                            />
                            <input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Tìm kiếm món ăn..."
                                class="h-11 w-full rounded-2xl border border-slate-200 bg-slate-50 pr-4 pl-10 text-sm text-slate-900 transition outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                            />
                        </div>
                        <div class="flex gap-2 overflow-x-auto pb-1">
                            <button
                                type="button"
                                class="shrink-0 rounded-xl px-3.5 py-2 text-xs font-bold transition"
                                :class="
                                    selectedCategoryId === 'all'
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                                @click="selectedCategoryId = 'all'"
                            >
                                Tất cả món
                            </button>
                            <button
                                v-for="category in categories"
                                :key="category.id"
                                type="button"
                                class="shrink-0 rounded-xl px-3.5 py-2 text-xs font-bold transition"
                                :class="
                                    selectedCategoryId === category.id
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                                @click="selectedCategoryId = category.id"
                            >
                                {{ category.name }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                >
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        type="button"
                        :disabled="isDisabled(product)"
                        class="group relative flex min-h-36 flex-col justify-between overflow-hidden rounded-2xl border p-4 text-left transition-all"
                        :class="[
                            isDisabled(product)
                                ? 'cursor-not-allowed border-slate-200 bg-slate-100 opacity-50 dark:border-slate-800 dark:bg-slate-950'
                                : selectedIds.includes(product.id)
                                  ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500/20 dark:border-indigo-400 dark:bg-indigo-950/30'
                                  : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900',
                        ]"
                        @click="toggleProduct(product)"
                    >
                        <span
                            class="text-[10px] font-bold tracking-wide text-slate-400 uppercase"
                        >
                            {{ product.category_name }}
                        </span>
                        <span
                            class="mt-3 line-clamp-2 text-sm font-black text-slate-900 dark:text-white"
                        >
                            {{ product.name }}
                        </span>
                        <span
                            class="mt-2 text-xs font-bold text-indigo-600 dark:text-indigo-400"
                        >
                            {{ numberFormat(product.price) }}đ
                        </span>

                        <span
                            v-if="product.is_unavailable"
                            class="mt-3 inline-flex items-center gap-1 rounded-lg bg-slate-200 px-2 py-1 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                        >
                            <Lock class="size-3" /> Đã khóa hôm nay
                        </span>
                        <span
                            v-else-if="
                                product.is_paused || !product.is_available
                            "
                            class="mt-3 inline-flex items-center gap-1 rounded-lg bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            <Lock class="size-3" /> Không khả dụng
                        </span>
                        <span
                            v-else-if="selectedIds.includes(product.id)"
                            class="mt-3 inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-2 py-1 text-[10px] font-bold text-white"
                        >
                            <Check class="size-3" /> Đã chọn
                        </span>
                    </button>
                </div>

                <div
                    v-if="filteredProducts.length === 0"
                    class="rounded-3xl border border-dashed border-slate-300 py-20 text-center text-sm text-slate-500 dark:border-slate-700"
                >
                    Không tìm thấy món phù hợp.
                </div>

                <div
                    class="sticky bottom-4 z-10 flex flex-col gap-3 rounded-2xl border border-indigo-200 bg-white/95 p-4 shadow-xl backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:border-indigo-900/50 dark:bg-slate-900/95"
                >
                    <div>
                        <p
                            class="text-sm font-black text-slate-900 dark:text-white"
                        >
                            Đã chọn {{ selectedIds.length }} món
                        </p>
                        <p
                            class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                        >
                            Các món đã khóa hôm nay sẽ không thể chọn lại.
                        </p>
                    </div>
                    <Button
                        class="h-11 rounded-xl bg-indigo-600 px-5 text-xs font-black text-white hover:bg-indigo-700"
                        :disabled="selectedIds.length === 0"
                        @click="goToConfirmation"
                    >
                        Xác nhận món đã chọn
                    </Button>
                </div>
            </div>

            <div v-else class="mx-auto max-w-3xl space-y-5">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 text-sm font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                    @click="goBackToSelection"
                >
                    <ChevronLeft class="size-4" /> Quay lại chọn món
                </button>

                <div
                    class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex items-start gap-3 border-b border-slate-100 pb-5 dark:border-slate-800"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400"
                        >
                            <AlertTriangle class="size-5" />
                        </div>
                        <div>
                            <h2
                                class="text-xl font-black text-slate-900 dark:text-white"
                            >
                                Xác nhận tạm ngưng món
                            </h2>
                            <p
                                class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                            >
                                Các món dưới đây sẽ bị làm mờ và không thể lên
                                đơn tại {{ branchName }} đến hết ngày
                                {{ today }}.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <div
                            v-for="product in selectedProducts"
                            :key="product.id"
                            class="flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50/60 px-3 py-3 dark:border-rose-900/50 dark:bg-rose-950/20"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-xl bg-rose-600 text-xs font-black text-white"
                            >
                                <Check class="size-4" />
                            </span>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-black text-slate-900 dark:text-white"
                                >
                                    {{ product.name }}
                                </p>
                                <p
                                    class="text-[11px] text-slate-500 dark:text-slate-400"
                                >
                                    {{ product.category_name }} ·
                                    {{ numberFormat(product.price) }}đ
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label
                            class="mb-2 block text-sm font-black text-slate-800 dark:text-slate-200"
                        >
                            Lý do tạm ngưng món
                            <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            v-model="reason"
                            rows="5"
                            maxlength="500"
                            placeholder="Ví dụ: Hết nguyên liệu, thiết bị hỏng, bếp không thể phục vụ món trong hôm nay..."
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-900 transition outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                        ></textarea>
                        <p class="mt-1 text-right text-[11px] text-slate-400">
                            {{ reason.length }}/500
                        </p>
                    </div>

                    <div
                        class="mt-5 flex flex-col-reverse gap-2 border-t border-slate-100 pt-5 sm:flex-row sm:justify-end dark:border-slate-800"
                    >
                        <Button
                            variant="outline"
                            class="h-11 rounded-xl px-5 text-xs font-bold"
                            :disabled="isSubmitting"
                            @click="goBackToSelection"
                        >
                            Chỉnh sửa lựa chọn
                        </Button>
                        <Button
                            class="h-11 rounded-xl bg-rose-600 px-5 text-xs font-black text-white hover:bg-rose-700"
                            :disabled="isSubmitting || reason.trim().length < 3"
                            @click="activateUnavailableProducts"
                        >
                            {{
                                isSubmitting
                                    ? 'Đang kích hoạt...'
                                    : 'Kích hoạt đến hết ngày'
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
