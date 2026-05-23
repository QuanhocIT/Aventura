<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    UtensilsCrossed, Plus, FolderPlus, Tag, DollarSign,
    CheckCircle2, AlertCircle, Sparkles, LayoutGrid, ListFilter
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({ layout: AppLayout });

type Category = { id: number; name: string; description: string | null };
type Product = { id: number; code: string; name: string; price: number; description: string | null; category: Category | null };

const props = defineProps<{
    categories: Category[];
    products: Product[];
}>();

const showAddCategory = ref(false);
const showAddProduct = ref(false);

const categoryForm = useForm({
    name: '',
    description: ''
});

const productForm = useForm({
    category_id: props.categories[0]?.id ? String(props.categories[0].id) : '',
    name: '',
    price: '',
    description: ''
});

const submitCategory = () => {
    categoryForm.post('/product-categories', {
        onSuccess: () => {
            categoryForm.reset();
            showAddCategory.value = false;
        }
    });
};

const submitProduct = () => {
    productForm.post('/products', {
        onSuccess: () => {
            productForm.reset();
            showAddProduct.value = false;
        }
    });
};

const formatCurrency = (val: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
};
</script>

<template>
    <Head title="Thực đơn & Món" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                    <UtensilsCrossed class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Thực Đơn & Món Ăn</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Quản lý cấu trúc thực đơn, nhóm món, giá bán sản phẩm thực tế của quán.
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Day 1 Tour Anchor Point: btn-add-category -->
                <Button
                    id="btn-add-category"
                    @click="showAddCategory = true"
                    variant="outline"
                    class="h-10 text-xs border-slate-200"
                >
                    <FolderPlus class="size-4 mr-2 text-indigo-600" />
                    Thêm nhóm món
                </Button>

                <!-- Day 1 Tour Anchor Point: btn-add-product -->
                <Button
                    id="btn-add-product"
                    @click="showAddProduct = true"
                    class="h-10 text-xs bg-rose-600 hover:bg-rose-700 text-white font-semibold"
                >
                    <Plus class="size-4 mr-2" />
                    Thêm món ăn
                </Button>
            </div>
        </div>

        <!-- Add Category Form Overlay Modal -->
        <div v-if="showAddCategory" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4" style="z-index: 9999;">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base">Tạo nhóm thực đơn mới</CardTitle>
                    <CardDescription>Phân loại món ăn giúp khách hàng order nhanh hơn.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitCategory" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="cat-name">Tên nhóm món <span class="text-rose-500">*</span></Label>
                            <Input id="cat-name" v-model="categoryForm.name" placeholder="Ví dụ: Món nướng, Trà sữa, Ăn vặt..." required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cat-desc">Mô tả nhóm</Label>
                            <textarea
                                id="cat-desc"
                                v-model="categoryForm.description"
                                rows="2"
                                placeholder="Ghi chú mô tả danh mục..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddCategory = false">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="categoryForm.processing">
                                {{ categoryForm.processing ? 'Đang tạo...' : 'Tạo nhóm món' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <!-- Add Product Form Overlay Modal -->
        <div v-if="showAddProduct" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4" style="z-index: 9999;">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base">Thêm món ăn mới vào thực đơn</CardTitle>
                    <CardDescription>Nhập thông tin chi tiết về sản phẩm để phục vụ bán hàng.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitProduct" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="prod-cat">Thuộc nhóm món</Label>
                            <select
                                id="prod-cat"
                                v-model="productForm.category_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                <option value="" disabled>Chọn một nhóm</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-name">Tên món ăn <span class="text-rose-500">*</span></Label>
                            <Input id="prod-name" v-model="productForm.name" placeholder="Ví dụ: Phở bò tái lăn, Trà đào cam sả..." required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-price">Giá bán (VND) <span class="text-rose-500">*</span></Label>
                            <Input id="prod-price" type="number" v-model="productForm.price" placeholder="Ví dụ: 45000" required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-desc">Mô tả món ăn</Label>
                            <textarea
                                id="prod-desc"
                                v-model="productForm.description"
                                rows="2"
                                placeholder="Ghi chú nguyên liệu, ghi chú nấu..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddProduct = false">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="productForm.processing">
                                {{ productForm.processing ? 'Đang thêm...' : 'Thêm món ăn' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Left sidebar: categories listing -->
            <div class="lg:col-span-1 flex flex-col gap-4">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3 flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-sm font-bold">Nhóm Món Ăn</CardTitle>
                            <CardDescription class="text-[11px]">Cơ cấu thực đơn</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <div v-if="categories.length" class="flex flex-col gap-1.5">
                            <div
                                v-for="cat in categories"
                                :key="cat.id"
                                class="p-3 rounded-xl border border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900 flex justify-between items-center text-xs"
                            >
                                <div>
                                    <p class="font-bold text-slate-800 dark:text-slate-200">{{ cat.name }}</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5 line-clamp-1">{{ cat.description ?? 'Không có mô tả.' }}</p>
                                </div>
                                <span class="text-[10px] bg-slate-200 dark:bg-slate-800 px-2 py-0.5 rounded-full font-semibold">
                                    {{ products.filter(p => p.category?.id === cat.id).length }} món
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-slate-400 text-xs">
                            <AlertCircle class="size-6 text-slate-300 mx-auto mb-1" />
                            Chưa có nhóm món ăn nào.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right content: products grid/list -->
            <div class="lg:col-span-3">
                <Card class="shadow-sm h-full">
                    <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-base">Danh sách món ăn thực tế ({{ products.length }})</CardTitle>
                            <CardDescription>Quét mã gọi món (QR Table) và hóa đơn sẽ đồng bộ với thực đơn này.</CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="products.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="p in products" :key="p.id" class="p-4 flex items-center justify-between hover:bg-slate-50/50 dark:hover:bg-slate-900/40 transition-colors">
                                <div class="flex items-start gap-3">
                                    <div class="size-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-bold text-xs text-slate-500">
                                        {{ p.name.substring(0,2).toUpperCase() }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-bold text-sm text-slate-800 dark:text-slate-200">{{ p.name }}</h4>
                                            <span class="text-[9px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wider bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-400">
                                                {{ p.category?.name ?? 'Chưa gán' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-1">{{ p.code }} · {{ p.description ?? 'Không có mô tả.' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-mono font-bold text-sm text-rose-600 dark:text-rose-400">{{ formatCurrency(p.price) }}</p>
                                    <span class="text-[9px] text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded-md dark:bg-emerald-950 dark:text-emerald-400 mt-1 inline-block">Đang kinh doanh</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center p-12 text-center text-slate-400">
                            <UtensilsCrossed class="size-12 text-slate-200 mb-3 animate-pulse" />
                            <p class="text-sm font-bold">Thực đơn trống</p>
                            <p class="text-xs mt-1 text-slate-500 max-w-sm">Dùng bong bóng hướng dẫn Guided Tour ở góc để thêm nhóm và món ăn thực tế đầu tiên của quán bạn!</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
