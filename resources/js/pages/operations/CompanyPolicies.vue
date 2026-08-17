<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    BookOpen,
    Edit3,
    Plus,
    Save,
    ShieldCheck,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import BackButton from '@/components/BackButton.vue';
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
    policies: Array<any>;
    branches: Array<any>;
    categories: Array<{
        id: number;
        code: string;
        name: string;
        is_system?: boolean;
    }>;
}>();

const isModalOpen = ref(false);
const isProcessing = ref(false);
const editingPolicy = ref<any>(null);
const categoryOptions = ref([...props.categories]);
const isCategoryModalOpen = ref(false);
const isCategoryProcessing = ref(false);
const newCategoryName = ref('');

const form = ref({
    title: '',
    category: 'general',
    content: '',
    suggested_fine_amount: 0,
    applies_to_all_branches: true,
    applicable_branch_ids: [] as Array<number>,
    status: 'published',
});

const openCreateModal = () => {
    editingPolicy.value = null;
    form.value = {
        title: '',
        category: 'general',
        content: '',
        suggested_fine_amount: 0,
        applies_to_all_branches: true,
        applicable_branch_ids: [],
        status: 'published',
    };
    isModalOpen.value = true;
};

const openEditModal = (p: any) => {
    editingPolicy.value = p;
    form.value = {
        title: p.title,
        category: p.category,
        content: p.content,
        suggested_fine_amount: p.suggested_fine_amount || 0,
        applies_to_all_branches: p.applies_to_all_branches,
        applicable_branch_ids: p.applicable_branch_ids || [],
        status: p.status,
    };
    isModalOpen.value = true;
};

const openCategoryModal = () => {
    newCategoryName.value = '';
    isCategoryModalOpen.value = true;
};

const submitCategory = async () => {
    const name = newCategoryName.value.trim();
    if (!name) {
        toast.error('Vui lòng nhập tên danh mục.');

        return;
    }

    isCategoryProcessing.value = true;

    try {
        const response = await axios.post('/api/company-policy-categories', {
            name,
        });
        const category = response.data.data;

        categoryOptions.value.push(category);
        form.value.category = category.code;
        isCategoryModalOpen.value = false;
        toast.success('Đã tạo danh mục kiểm soát mới.');
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Không thể tạo danh mục kiểm soát.',
        );
    } finally {
        isCategoryProcessing.value = false;
    }
};

const submitForm = async () => {
    if (!form.value.title.trim() || !form.value.content.trim()) {
        toast.error('Vui lòng điền Tên quy định và Nội dung chi tiết.');

        return;
    }

    isProcessing.value = true;

    try {
        if (editingPolicy.value) {
            await axios.put(
                `/api/company-policies/${editingPolicy.value.id}`,
                form.value,
            );
            toast.success('Đã cập nhật quy định tiêu chuẩn!');
        } else {
            await axios.post('/api/company-policies', form.value);
            toast.success('Đã ban hành quy định tiêu chuẩn mới!');
        }

        isModalOpen.value = false;
        router.reload();
    } catch (e: any) {
        toast.error(
            e.response?.data?.message || 'Có lỗi xảy ra khi lưu quy định.',
        );
    } finally {
        isProcessing.value = false;
    }
};

const deletePolicy = async (id: number) => {
    if (!confirm('Bạn có chắc chắn muốn xóa quy định tiêu chuẩn này?')) {
        return;
    }

    try {
        await axios.delete(`/api/company-policies/${id}`);
        toast.success('Đã xóa quy định.');
        router.reload();
    } catch (e: any) {
        toast.error(e.response?.data?.message || 'Không thể xóa quy định.');
    }
};

const getCategoryLabel = (cat: string) => {
    return (
        categoryOptions.value.find((category) => category.code === cat)?.name ??
        'Quy Định Chung'
    );
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(amount || 0);
};

const publishedCount = computed(
    () =>
        props.policies.filter((policy) => policy.status === 'published').length,
);
const chainWideCount = computed(
    () =>
        props.policies.filter((policy) => policy.applies_to_all_branches)
            .length,
);

const getCategoryTone = (category: string) => {
    switch (category) {
        case 'food_safety':
            return 'border-emerald-200/70 bg-emerald-500/10 text-emerald-700 dark:border-emerald-500/20 dark:text-emerald-300';
        case 'service_attitude':
            return 'border-sky-200/70 bg-sky-500/10 text-sky-700 dark:border-sky-500/20 dark:text-sky-300';
        case 'inventory_storage':
            return 'border-amber-200/70 bg-amber-500/10 text-amber-700 dark:border-amber-500/20 dark:text-amber-300';
        case 'pos_cashier':
            return 'border-violet-200/70 bg-violet-500/10 text-violet-700 dark:border-violet-500/20 dark:text-violet-300';
        case 'uniform_time':
            return 'border-orange-200/70 bg-orange-500/10 text-orange-700 dark:border-orange-500/20 dark:text-orange-300';
        default:
            return 'border-indigo-200/70 bg-indigo-500/10 text-indigo-700 dark:border-indigo-500/20 dark:text-indigo-300';
    }
};
</script>

<template>
    <Head title="Quản Lý Bộ Quy Định & Tiêu Chuẩn Vận Hành" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">
        <section
            class="relative overflow-hidden rounded-3xl border border-indigo-200/70 bg-gradient-to-br from-indigo-50 via-background to-sky-50 px-5 py-5 shadow-sm md:px-7 md:py-6 dark:border-indigo-500/20 dark:from-indigo-950/50 dark:via-background dark:to-sky-950/20"
        >
            <div
                class="pointer-events-none absolute -top-20 -right-16 size-56 rounded-full bg-indigo-500/10 blur-3xl dark:bg-indigo-500/20"
            />
            <div
                class="relative flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex items-start gap-4">
                    <BackButton fallback-href="/dashboard" label="Trang chủ" />
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20"
                    >
                        <BookOpen class="size-6" />
                    </div>
                    <div>
                        <p
                            class="mb-1 text-[10px] font-bold tracking-[0.18em] text-indigo-600 uppercase dark:text-indigo-300"
                        >
                            Bộ vận hành chuỗi
                        </p>
                        <h1
                            class="text-2xl font-black tracking-tight text-foreground md:text-3xl"
                        >
                            Bộ Quy Định & Tiêu Chuẩn
                        </h1>
                        <p
                            class="mt-1 max-w-2xl text-sm leading-6 text-muted-foreground"
                        >
                            Chuẩn hóa cách phục vụ, vệ sinh, kiểm kho và định
                            mức xử lý cho từng chi nhánh.
                        </p>
                    </div>
                </div>

                <Button
                    @click="openCreateModal"
                    class="h-10 shrink-0 gap-2 rounded-xl bg-indigo-600 px-4 text-xs font-bold text-white shadow-md shadow-indigo-600/20 hover:bg-indigo-700"
                >
                    <Plus class="size-4" /> Ban hành quy định
                </Button>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Card class="border-indigo-200/60 dark:border-indigo-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-300"
                        >Tổng quy định</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"
                    >
                        <BookOpen class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ policies.length }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        tiêu chuẩn trong hệ thống
                    </p></CardContent
                >
            </Card>
            <Card class="border-emerald-200/60 dark:border-emerald-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-300"
                        >Đã ban hành</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-300"
                    >
                        <ShieldCheck class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ publishedCount }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        đang được áp dụng
                    </p></CardContent
                >
            </Card>
            <Card class="border-sky-200/60 dark:border-sky-500/20">
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-[11px] font-bold tracking-wider text-sky-600 uppercase dark:text-sky-300"
                        >Áp dụng toàn chuỗi</CardDescription
                    >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-300"
                    >
                        <ShieldCheck class="size-4" />
                    </div>
                </CardHeader>
                <CardContent class="pb-5"
                    ><p class="text-3xl font-black text-foreground">
                        {{ chainWideCount }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        quy định cho mọi chi nhánh
                    </p></CardContent
                >
            </Card>
        </div>

        <section class="space-y-4">
            <div
                class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold tracking-tight text-foreground"
                    >
                        Danh mục tiêu chuẩn
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Các quy định được dùng làm căn cứ kiểm tra và xử lý vi
                        phạm.
                    </p>
                </div>
                <span class="text-xs font-semibold text-muted-foreground"
                    >{{ policies.length }} quy định</span
                >
            </div>

            <div
                v-if="policies.length"
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <Card
                    v-for="p in policies"
                    :key="p.id"
                    class="group overflow-hidden border-border/70 p-0"
                >
                    <div
                        class="h-1 bg-gradient-to-r from-indigo-500 to-sky-400 opacity-80"
                    />
                    <CardHeader
                        class="gap-3 border-b border-border/60 bg-muted/15 px-5 py-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="rounded-lg bg-indigo-500/10 px-2 py-1 font-mono text-[10px] font-bold tracking-wide text-indigo-600 dark:text-indigo-300"
                                >{{ p.policy_code }}</span
                            >
                            <span
                                :class="[
                                    'rounded-full border px-2.5 py-1 text-[10px] font-bold',
                                    getCategoryTone(p.category),
                                ]"
                                >{{ getCategoryLabel(p.category) }}</span
                            >
                        </div>
                        <div>
                            <CardTitle
                                class="line-clamp-2 text-base leading-6 font-bold text-foreground"
                                >{{ p.title }}</CardTitle
                            >
                            <CardDescription class="mt-1 text-xs"
                                >Phạm vi:
                                {{
                                    p.applies_to_all_branches
                                        ? 'Tất cả chi nhánh'
                                        : `${p.applicable_branch_ids?.length || 0} chi nhánh chỉ định`
                                }}</CardDescription
                            >
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-4 px-5 py-4">
                        <p
                            class="line-clamp-4 min-h-20 text-xs leading-5 whitespace-pre-line text-muted-foreground"
                        >
                            {{ p.content }}
                        </p>
                        <div
                            v-if="p.suggested_fine_amount > 0"
                            class="flex items-center justify-between gap-3 rounded-xl border border-rose-200/70 bg-rose-500/5 px-3 py-2.5 text-[11px] dark:border-rose-500/20"
                        >
                            <span
                                class="font-semibold text-rose-700 dark:text-rose-300"
                                >Định mức phạt</span
                            >
                            <span
                                class="font-mono font-bold text-rose-700 dark:text-rose-300"
                                >{{
                                    formatCurrency(p.suggested_fine_amount)
                                }}</span
                            >
                        </div>
                    </CardContent>
                    <div
                        class="flex items-center justify-between border-t border-border/60 bg-muted/10 px-4 py-3"
                    >
                        <span
                            :class="[
                                'flex items-center gap-1.5 text-[11px] font-semibold',
                                p.status === 'published'
                                    ? 'text-emerald-600 dark:text-emerald-300'
                                    : 'text-muted-foreground',
                            ]"
                        >
                            <ShieldCheck class="size-3.5" />
                            {{
                                p.status === 'published'
                                    ? 'Đã ban hành'
                                    : 'Bản nháp'
                            }}
                        </span>
                        <div class="flex items-center gap-1">
                            <Button
                                @click="openEditModal(p)"
                                variant="ghost"
                                size="sm"
                                class="h-8 gap-1 rounded-lg px-2 text-xs text-muted-foreground hover:text-foreground"
                                ><Edit3 class="size-3.5" /> Sửa</Button
                            >
                            <Button
                                @click="deletePolicy(p.id)"
                                variant="ghost"
                                size="sm"
                                class="size-8 rounded-lg p-0 text-rose-500 hover:bg-rose-500/10 hover:text-rose-600"
                                ><Trash2 class="size-3.5"
                            /></Button>
                        </div>
                    </div>
                </Card>
            </div>

            <Card v-else class="border-dashed border-border/80">
                <CardContent
                    class="flex flex-col items-center justify-center px-6 py-14 text-center"
                >
                    <div
                        class="mb-4 flex size-14 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"
                    >
                        <BookOpen class="size-7" />
                    </div>
                    <h3 class="font-bold text-foreground">
                        Chưa có quy định nào
                    </h3>
                    <p
                        class="mt-1 max-w-sm text-xs leading-5 text-muted-foreground"
                    >
                        Tạo quy định đầu tiên để các chi nhánh có cùng một chuẩn
                        vận hành.
                    </p>
                    <Button
                        @click="openCreateModal"
                        class="mt-5 h-9 gap-2 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                        ><Plus class="size-4" /> Tạo quy định đầu tiên</Button
                    >
                </CardContent>
            </Card>
        </section>

        <!-- Create / Edit Policy Modal -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-border bg-background text-foreground shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border bg-muted/30 p-5"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex size-9 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-300"
                        >
                            <BookOpen class="size-5" />
                        </div>
                        <h3 class="text-sm font-bold">
                            {{
                                editingPolicy
                                    ? 'Cập nhật bộ quy định'
                                    : 'Ban hành quy định & tiêu chuẩn mới'
                            }}
                        </h3>
                    </div>
                    <button
                        @click="isModalOpen = false"
                        class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="max-h-[75vh] space-y-5 overflow-y-auto p-6 text-xs">
                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Tên quy định / tiêu chuẩn (*)</label
                        >
                        <Input
                            v-model="form.title"
                            placeholder="VD: Quy định vệ sinh cá nhân & Tiêu chuẩn phục vụ bàn..."
                            class="text-xs"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-2">
                                <label class="font-semibold text-foreground"
                                    >Danh mục kiểm soát</label
                                >
                                <button
                                    type="button"
                                    @click="openCategoryModal"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-indigo-600 transition hover:bg-indigo-500/10 dark:text-indigo-300"
                                >
                                    <Plus class="size-3.5" /> Tạo danh mục
                                </button>
                            </div>
                            <select
                                v-model="form.category"
                                class="w-full rounded-xl border border-input bg-background p-2.5 font-medium text-foreground outline-none focus:ring-2 focus:ring-indigo-500/30"
                            >
                                <option
                                    v-for="category in categoryOptions"
                                    :key="category.id"
                                    :value="category.code"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block font-semibold text-foreground"
                                >Mức phạt tham chiếu (VNĐ)</label
                            >
                            <Input
                                v-model.number="form.suggested_fine_amount"
                                type="number"
                                step="50000"
                                class="text-xs"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block font-semibold text-foreground"
                            >Nội dung chi tiết điều khoản (*)</label
                        >
                        <textarea
                            v-model="form.content"
                            rows="6"
                            placeholder="Nhập nội dung tiêu chuẩn vận hành, danh mục các hành vi vi phạm và hình thức xử lý..."
                            class="min-h-32 w-full rounded-xl border border-input bg-background p-3 text-xs leading-relaxed text-foreground outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-indigo-500/30"
                        ></textarea>
                    </div>

                    <div
                        class="space-y-3 rounded-2xl border border-border bg-muted/20 p-4"
                    >
                        <label class="block font-bold text-foreground"
                            >Phạm vi áp dụng chi nhánh</label
                        >
                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="form.applies_to_all_branches"
                                class="h-4 w-4 rounded accent-indigo-600"
                            />
                            <span class="font-medium text-foreground"
                                >Áp dụng cho tất cả chi nhánh toàn chuỗi</span
                            >
                        </div>

                        <div
                            v-if="!form.applies_to_all_branches"
                            class="space-y-2 border-t border-border pt-3"
                        >
                            <p class="mb-1 text-muted-foreground">
                                Chọn các chi nhánh áp dụng:
                            </p>
                            <div class="grid grid-cols-2 gap-2">
                                <label
                                    v-for="b in branches"
                                    :key="b.id"
                                    class="flex items-center gap-2 rounded-xl border border-border bg-background p-2"
                                >
                                    <input
                                        type="checkbox"
                                        :value="b.id"
                                        v-model="form.applicable_branch_ids"
                                        class="accent-indigo-600"
                                    />
                                    <span>{{ b.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-border bg-muted/20 p-4"
                >
                    <Button
                        @click="isModalOpen = false"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        >Hủy</Button
                    >
                    <Button
                        @click="submitForm"
                        size="sm"
                        :disabled="isProcessing"
                        class="gap-1.5 rounded-xl bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                    >
                        <Save class="h-4 w-4" />
                        {{
                            editingPolicy
                                ? 'Cập Nhật Quy Định'
                                : 'Ban Hành Quy Định'
                        }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Create control category modal -->
        <div
            v-if="isCategoryModalOpen"
            class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-md rounded-2xl border border-border bg-background p-5 text-foreground shadow-2xl"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-bold">Tạo danh mục kiểm soát</h3>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Danh mục mới sẽ xuất hiện ngay trong form ban hành quy
                            định.
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="isCategoryModalOpen = false"
                        class="rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="mt-5">
                    <label class="mb-1.5 block text-xs font-semibold"
                        >Tên danh mục (*)</label
                    >
                    <Input
                        v-model="newCategoryName"
                        autofocus
                        placeholder="VD: An toàn điện & Phòng cháy chữa cháy"
                        class="text-xs"
                        @keyup.enter="submitCategory"
                    />
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-xs"
                        @click="isCategoryModalOpen = false"
                        >Hủy</Button
                    >
                    <Button
                        type="button"
                        size="sm"
                        class="gap-1.5 rounded-xl bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                        :disabled="isCategoryProcessing"
                        @click="submitCategory"
                    >
                        <Save class="size-3.5" />
                        Tạo danh mục
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
