<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    UtensilsCrossed,
    Plus,
    FolderPlus,
    Tag,
    DollarSign,
    CheckCircle2,
    AlertCircle,
    Sparkles,
    LayoutGrid,
    ListFilter,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({ layout: AppLayout });

type Category = { id: number; name: string; description: string | null };
type Product = {
    id: number;
    code: string;
    name: string;
    price: number;
    description: string | null;
    category: Category | null;
};

const props = defineProps<{
    categories: Category[];
    products: Product[];
}>();

const showAddCategory = ref(false);
const showAddProduct = ref(false);

const categoryForm = useForm({
    name: '',
    description: '',
});

const productForm = useForm({
    category_id: props.categories[0]?.id ? String(props.categories[0].id) : '',
    name: '',
    price: '',
    description: '',
});

const submitCategory = () => {
    categoryForm.post('/product-categories', {
        onSuccess: () => {
            categoryForm.reset();
            showAddCategory.value = false;
        },
    });
};

const submitProduct = () => {
    productForm.post('/products', {
        onSuccess: () => {
            productForm.reset();
            showAddProduct.value = false;
        },
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
};
</script>

<template>
    <Head title="Thực đơn & Món" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400"
                >
                    <UtensilsCrossed class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Thực Đơn & Món Ăn
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Quản lý cấu trúc thực đơn, nhóm món, giá bán sản phẩm
                        thực tế của quán.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Day 1 Tour Anchor Point: btn-add-category -->
                <Button
                    id="btn-add-category"
                    @click="showAddCategory = true"
                    variant="outline"
                    class="h-10 border-slate-200 text-xs"
                >
                    <FolderPlus class="mr-2 size-4 text-indigo-600" />
                    Thêm nhóm món
                </Button>

                <!-- Day 1 Tour Anchor Point: btn-add-product -->
                <Button
                    id="btn-add-product"
                    @click="showAddProduct = true"
                    class="h-10 bg-rose-600 text-xs font-semibold text-white hover:bg-rose-700"
                >
                    <Plus class="mr-2 size-4" />
                    Thêm món ăn
                </Button>
            </div>
        </div>

        <!-- Add Category Form Overlay Modal -->
        <div
            v-if="showAddCategory"
            class="fixed inset-0 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
            style="z-index: 9999"
        >
            <Card
                class="w-full max-w-md animate-in duration-150 zoom-in-95 fade-in"
            >
                <CardHeader>
                    <CardTitle class="text-base"
                        >Tạo nhóm thực đơn mới</CardTitle
                    >
                    <CardDescription
                        >Phân loại món ăn giúp khách hàng order nhanh
                        hơn.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitCategory" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="cat-name"
                                >Tên nhóm món
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="cat-name"
                                v-model="categoryForm.name"
                                placeholder="Ví dụ: Món nướng, Trà sữa, Ăn vặt..."
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cat-desc">Mô tả nhóm</Label>
                            <textarea
                                id="cat-desc"
                                v-model="categoryForm.description"
                                rows="2"
                                placeholder="Ghi chú mô tả danh mục..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showAddCategory = false"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-indigo-600 text-white"
                                :disabled="categoryForm.processing"
                            >
                                {{
                                    categoryForm.processing
                                        ? 'Đang tạo...'
                                        : 'Tạo nhóm món'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Add Product Form Overlay Modal -->
        <div
            v-if="showAddProduct"
            class="fixed inset-0 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
            style="z-index: 9999"
        >
            <Card
                class="w-full max-w-md animate-in duration-150 zoom-in-95 fade-in"
            >
                <CardHeader>
                    <CardTitle class="text-base"
                        >Thêm món ăn mới vào thực đơn</CardTitle
                    >
                    <CardDescription
                        >Nhập thông tin chi tiết về sản phẩm để phục vụ bán
                        hàng.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitProduct" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="prod-cat">Thuộc nhóm món</Label>
                            <select
                                id="prod-cat"
                                v-model="productForm.category_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            >
                                <option value="" disabled>Chọn một nhóm</option>
                                <option
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-name"
                                >Tên món ăn
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="prod-name"
                                v-model="productForm.name"
                                placeholder="Ví dụ: Phở bò tái lăn, Trà đào cam sả..."
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-price"
                                >Giá bán (VND)
                                <span class="text-rose-500">*</span></Label
                            >
                            <Input
                                id="prod-price"
                                type="number"
                                v-model="productForm.price"
                                placeholder="Ví dụ: 45000"
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-desc">Mô tả món ăn</Label>
                            <textarea
                                id="prod-desc"
                                v-model="productForm.description"
                                rows="2"
                                placeholder="Ghi chú nguyên liệu, ghi chú nấu..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                @click="showAddProduct = false"
                                >Hủy</Button
                            >
                            <Button
                                type="submit"
                                class="bg-indigo-600 text-white"
                                :disabled="productForm.processing"
                            >
                                {{
                                    productForm.processing
                                        ? 'Đang thêm...'
                                        : 'Thêm món ăn'
                                }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
            <!-- Left sidebar: categories listing -->
            <div class="flex flex-col gap-4 lg:col-span-1">
                <Card class="shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between pb-3"
                    >
                        <div>
                            <CardTitle class="text-sm font-bold"
                                >Nhóm Món Ăn</CardTitle
                            >
                            <CardDescription class="text-[11px]"
                                >Cơ cấu thực đơn</CardDescription
                            >
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <div
                            v-if="categories.length"
                            class="flex flex-col gap-1.5"
                        >
                            <div
                                v-for="cat in categories"
                                :key="cat.id"
                                class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-3 text-xs dark:border-slate-800 dark:bg-slate-900"
                            >
                                <div>
                                    <p
                                        class="font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ cat.name }}
                                    </p>
                                    <p
                                        class="mt-0.5 line-clamp-1 text-[10px] text-slate-400"
                                    >
                                        {{
                                            cat.description ?? 'Không có mô tả.'
                                        }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-semibold dark:bg-slate-800"
                                >
                                    {{
                                        products.filter(
                                            (p) => p.category?.id === cat.id,
                                        ).length
                                    }}
                                    món
                                </span>
                            </div>
                        </div>
                        <div
                            v-else
                            class="py-6 text-center text-xs text-slate-400"
                        >
                            <AlertCircle
                                class="mx-auto mb-1 size-6 text-slate-300"
                            />
                            Chưa có nhóm món ăn nào.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right content: products grid/list -->
            <div class="lg:col-span-3">
                <Card class="h-full shadow-sm">
                    <CardHeader
                        class="flex flex-row items-center justify-between border-b pb-3"
                    >
                        <div>
                            <CardTitle class="text-base"
                                >Danh sách món ăn thực tế ({{
                                    products.length
                                }})</CardTitle
                            >
                            <CardDescription
                                >Quét mã gọi món (QR Table) và hóa đơn sẽ đồng
                                bộ với thực đơn này.</CardDescription
                            >
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="products.length"
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <div
                                v-for="p in products"
                                :key="p.id"
                                class="flex items-center justify-between p-4 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/40"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex size-10 items-center justify-center rounded-xl bg-slate-100 text-xs font-bold text-slate-500 dark:bg-slate-800"
                                    >
                                        {{
                                            p.name.substring(0, 2).toUpperCase()
                                        }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4
                                                class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                            >
                                                {{ p.name }}
                                            </h4>
                                            <span
                                                class="rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-bold tracking-wider text-rose-700 uppercase dark:bg-rose-950 dark:text-rose-400"
                                            >
                                                {{
                                                    p.category?.name ??
                                                    'Chưa gán'
                                                }}
                                            </span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-400">
                                            {{ p.code }} ·
                                            {{
                                                p.description ??
                                                'Không có mô tả.'
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p
                                        class="font-mono text-sm font-bold text-rose-600 dark:text-rose-400"
                                    >
                                        {{ formatCurrency(p.price) }}
                                    </p>
                                    <span
                                        class="mt-1 inline-block rounded-md bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400"
                                        >Đang kinh doanh</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center p-12 text-center text-slate-400"
                        >
                            <UtensilsCrossed
                                class="mb-3 size-12 animate-pulse text-slate-200"
                            />
                            <p class="text-sm font-bold">Thực đơn trống</p>
                            <p class="mt-1 max-w-sm text-xs text-slate-500">
                                Dùng bong bóng hướng dẫn Guided Tour ở góc để
                                thêm nhóm và món ăn thực tế đầu tiên của quán
                                bạn!
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
