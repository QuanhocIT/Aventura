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
    <Head title="Thá»±c Ä‘Æ¡n & MÃ³n" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                    <UtensilsCrossed class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Thá»±c ÄÆ¡n & MÃ³n Ä‚n</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Quáº£n lÃ½ cáº¥u trÃºc thá»±c Ä‘Æ¡n, nhÃ³m mÃ³n, giÃ¡ bÃ¡n sáº£n pháº©m thá»±c táº¿ cá»§a quÃ¡n.
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
                    ThÃªm nhÃ³m mÃ³n
                </Button>

                <!-- Day 1 Tour Anchor Point: btn-add-product -->
                <Button
                    id="btn-add-product"
                    @click="showAddProduct = true"
                    class="h-10 text-xs bg-rose-600 hover:bg-rose-700 text-white font-semibold"
                >
                    <Plus class="size-4 mr-2" />
                    ThÃªm mÃ³n Äƒn
                </Button>
            </div>
        </div>

        <!-- Add Category Form Overlay Modal -->
        <div v-if="showAddCategory" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4" style="z-index: 9999;">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base">Táº¡o nhÃ³m thá»±c Ä‘Æ¡n má»›i</CardTitle>
                    <CardDescription>PhÃ¢n loáº¡i mÃ³n Äƒn giÃºp khÃ¡ch hÃ ng order nhanh hÆ¡n.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitCategory" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="cat-name">TÃªn nhÃ³m mÃ³n <span class="text-rose-500">*</span></Label>
                            <Input id="cat-name" v-model="categoryForm.name" placeholder="VÃ­ dá»¥: MÃ³n nÆ°á»›ng, TrÃ  sá»¯a, Ä‚n váº·t..." required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="cat-desc">MÃ´ táº£ nhÃ³m</Label>
                            <textarea
                                id="cat-desc"
                                v-model="categoryForm.description"
                                rows="2"
                                placeholder="Ghi chÃº mÃ´ táº£ danh má»¥c..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddCategory = false">Há»§y</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="categoryForm.processing">
                                {{ categoryForm.processing ? 'Äang táº¡o...' : 'Táº¡o nhÃ³m mÃ³n' }}
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
                    <CardTitle class="text-base">ThÃªm mÃ³n Äƒn má»›i vÃ o thá»±c Ä‘Æ¡n</CardTitle>
                    <CardDescription>Nháº­p thÃ´ng tin chi tiáº¿t vá» sáº£n pháº©m Ä‘á»ƒ phá»¥c vá»¥ bÃ¡n hÃ ng.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitProduct" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="prod-cat">Thuá»™c nhÃ³m mÃ³n</Label>
                            <select
                                id="prod-cat"
                                v-model="productForm.category_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                <option value="" disabled>Chá»n má»™t nhÃ³m</option>
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-name">TÃªn mÃ³n Äƒn <span class="text-rose-500">*</span></Label>
                            <Input id="prod-name" v-model="productForm.name" placeholder="VÃ­ dá»¥: Phá»Ÿ bÃ² tÃ¡i lÄƒn, TrÃ  Ä‘Ã o cam sáº£..." required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-price">GiÃ¡ bÃ¡n (VND) <span class="text-rose-500">*</span></Label>
                            <Input id="prod-price" type="number" v-model="productForm.price" placeholder="VÃ­ dá»¥: 45000" required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="prod-desc">MÃ´ táº£ mÃ³n Äƒn</Label>
                            <textarea
                                id="prod-desc"
                                v-model="productForm.description"
                                rows="2"
                                placeholder="Ghi chÃº nguyÃªn liá»‡u, ghi chÃº náº¥u..."
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddProduct = false">Há»§y</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="productForm.processing">
                                {{ productForm.processing ? 'Äang thÃªm...' : 'ThÃªm mÃ³n Äƒn' }}
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
                            <CardTitle class="text-sm font-bold">NhÃ³m MÃ³n Ä‚n</CardTitle>
                            <CardDescription class="text-[11px]">CÆ¡ cáº¥u thá»±c Ä‘Æ¡n</CardDescription>
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
                                    <p class="text-[10px] text-slate-400 mt-0.5 line-clamp-1">{{ cat.description ?? 'KhÃ´ng cÃ³ mÃ´ táº£.' }}</p>
                                </div>
                                <span class="text-[10px] bg-slate-200 dark:bg-slate-800 px-2 py-0.5 rounded-full font-semibold">
                                    {{ products.filter(p => p.category?.id === cat.id).length }} mÃ³n
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-slate-400 text-xs">
                            <AlertCircle class="size-6 text-slate-300 mx-auto mb-1" />
                            ChÆ°a cÃ³ nhÃ³m mÃ³n Äƒn nÃ o.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right content: products grid/list -->
            <div class="lg:col-span-3">
                <Card class="shadow-sm h-full">
                    <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                        <div>
                            <CardTitle class="text-base">Danh sÃ¡ch mÃ³n Äƒn thá»±c táº¿ ({{ products.length }})</CardTitle>
                            <CardDescription>QuÃ©t mÃ£ gá»i mÃ³n (QR Table) vÃ  hÃ³a Ä‘Æ¡n sáº½ Ä‘á»“ng bá»™ vá»›i thá»±c Ä‘Æ¡n nÃ y.</CardDescription>
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
                                                {{ p.category?.name ?? 'ChÆ°a gÃ¡n' }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-1">{{ p.code }} Â· {{ p.description ?? 'KhÃ´ng cÃ³ mÃ´ táº£.' }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-mono font-bold text-sm text-rose-600 dark:text-rose-400">{{ formatCurrency(p.price) }}</p>
                                    <span class="text-[9px] text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded-md dark:bg-emerald-950 dark:text-emerald-400 mt-1 inline-block">Äang kinh doanh</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center p-12 text-center text-slate-400">
                            <UtensilsCrossed class="size-12 text-slate-200 mb-3 animate-pulse" />
                            <p class="text-sm font-bold">Thá»±c Ä‘Æ¡n trá»‘ng</p>
                            <p class="text-xs mt-1 text-slate-500 max-w-sm">DÃ¹ng bong bÃ³ng hÆ°á»›ng dáº«n Guided Tour á»Ÿ gÃ³c Ä‘á»ƒ thÃªm nhÃ³m vÃ  mÃ³n Äƒn thá»±c táº¿ Ä‘áº§u tiÃªn cá»§a quÃ¡n báº¡n!</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
