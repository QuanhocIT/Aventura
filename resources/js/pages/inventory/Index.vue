<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    Package, Plus, Settings2, BarChart2, CheckCircle2,
    AlertCircle, Sparkles, Scale, Info
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineOptions({ layout: AppLayout });

type Ingredient = { id: number; sku: string | null; name: string; category_name: string | null; average_cost: number; unit: { id: number; symbol: string } | null };
type RecipeItem = { id: number; ingredient_name: string; quantity: number; unit_symbol: string; waste_rate: number };
type Product = { id: number; name: string; code: string; price: number; recipes: RecipeItem[] };
type Unit = { id: number; name: string; symbol: string };

const props = defineProps<{
    ingredients: Ingredient[];
    products: Product[];
    units: Unit[];
}>();

const showAddRecipe = ref(false);
const activeProduct = ref<Product | null>(null);

const recipeForm = useForm({
    product_id: '',
    ingredient_id: props.ingredients[0]?.id ? String(props.ingredients[0].id) : '',
    quantity: '',
    waste_rate: '0'
});

const openAddRecipeModal = (prod: Product) => {
    activeProduct.value = prod;
    recipeForm.product_id = String(prod.id);
    showAddRecipe.value = true;
};

const submitRecipe = () => {
    recipeForm.post('/inventory/recipes', {
        onSuccess: () => {
            recipeForm.reset('quantity', 'waste_rate');
            showAddRecipe.value = false;
            // Cập nhật lại activeProduct
            if (activeProduct.value) {
                const updated = props.products.find(p => p.id === activeProduct.value?.id);
                if (updated) {
                    activeProduct.value = updated;
                }
            }
        }
    });
};
</script>

<template>
    <Head title="Kho & Định lượng nguyên liệu" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Package class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Kho Nguyên Liệu & Công Thức Định Lượng</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Cấu hình nguyên liệu thô và công thức định lượng (Recipes) cho từng món ăn để tự động trừ kho khi bán hàng.
                    </p>
                </div>
            </div>
        </div>

        <!-- Add Recipe Form Modal Overlay -->
        <div v-if="showAddRecipe && activeProduct" class="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4" style="z-index: 9999;">
            <Card class="max-w-md w-full animate-in fade-in zoom-in-95 duration-150">
                <CardHeader>
                    <CardTitle class="text-base flex items-center gap-2">
                        <Scale class="size-5 text-indigo-600" />
                        Thiết lập định lượng món: {{ activeProduct.name }}
                    </CardTitle>
                    <CardDescription>Cài đặt khối lượng/thể tích nguyên liệu hao hụt thực tế.</CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitRecipe" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="recipe-ing">Chọn Nguyên Liệu Thô <span class="text-rose-500">*</span></Label>
                            <select
                                id="recipe-ing"
                                v-model="recipeForm.ingredient_id"
                                required
                                class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                            >
                                <option value="" disabled>Chọn nguyên liệu</option>
                                <option v-for="ing in ingredients" :key="ing.id" :value="ing.id">
                                    {{ ing.name }} ({{ ing.unit?.symbol ?? 'đơn vị' }})
                                </option>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-1.5">
                                <Label for="recipe-qty">Số lượng định lượng <span class="text-rose-500">*</span></Label>
                                <div class="relative">
                                    <Input id="recipe-qty" type="number" step="0.001" v-model="recipeForm.quantity" placeholder="Ví dụ: 150" required />
                                    <span class="absolute right-3 top-2 text-xs font-bold text-slate-400">
                                        {{ ingredients.find(i => i.id === Number(recipeForm.ingredient_id))?.unit?.symbol ?? 'đơn vị' }}
                                    </span>
                                </div>
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="recipe-waste">Tỉ lệ hao hụt (%)</Label>
                                <Input id="recipe-waste" type="number" v-model="recipeForm.waste_rate" placeholder="Mặc định: 0" />
                            </div>
                        </div>

                        <div class="p-3 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-100 rounded-xl flex items-start gap-2 text-[11px] text-indigo-700 dark:text-indigo-400">
                            <Info class="size-4 shrink-0 mt-0.5" />
                            <p>Khi nhân viên thanh toán món này, hệ thống sẽ tự động nhân số lượng định lượng và trừ trực tiếp vào thẻ kho tương ứng.</p>
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <Button type="button" variant="outline" @click="showAddRecipe = false">Hủy</Button>
                            <Button type="submit" class="bg-indigo-600 text-white" :disabled="recipeForm.processing">
                                {{ recipeForm.processing ? 'Đang lưu...' : 'Lưu công thức' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left sidebar: Raw Ingredients listing -->
            <div class="lg:col-span-1">
                <Card class="shadow-sm h-full">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-sm font-bold">Danh Mục Nguyên Liệu Thô ({{ ingredients.length }})</CardTitle>
                        <CardDescription class="text-[11px]">Nguyên liệu nhập từ nhà cung cấp</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0 divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-if="ingredients.length">
                            <div v-for="ing in ingredients" :key="ing.id" class="p-3 flex justify-between items-center text-xs">
                                <div>
                                    <p class="font-bold text-slate-800 dark:text-slate-200">{{ ing.name }}</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">{{ ing.sku ?? 'SKU-NONE' }} · {{ ing.category_name ?? 'Nguyên liệu' }}</p>
                                </div>
                                <span class="text-[10px] font-mono bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-full font-bold">
                                    Đơn vị: {{ ing.unit?.symbol ?? 'đơn vị' }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-slate-400 text-xs">
                            Chưa có nguyên liệu thô nào được nhập.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right content: Product Recipes formulation list -->
            <div class="lg:col-span-2">
                <Card class="shadow-sm">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-base flex items-center gap-1.5">
                            <Scale class="size-5 text-indigo-600" />
                            Thiết Lập Công Thức Định Lượng Cho Món Ăn
                        </CardTitle>
                        <CardDescription>Cơ chế khấu trừ kho tự động sẽ chỉ áp dụng trên các món đã lập công thức định lượng dưới đây.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="products.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <div v-for="p in products" :key="p.id" class="p-5 flex flex-col gap-3 hover:bg-slate-50/20 transition-colors">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-800 dark:text-slate-200">{{ p.name }}</h4>
                                        <p class="text-[10px] text-slate-400">{{ p.code }}</p>
                                    </div>
                                    <!-- Day 2 Tour Target Class: btn-set-recipe -->
                                    <Button
                                        @click="openAddRecipeModal(p)"
                                        size="sm"
                                        variant="outline"
                                        class="btn-set-recipe h-8 text-xs border-indigo-200 text-indigo-600 hover:bg-indigo-50 font-bold"
                                    >
                                        <Settings2 class="size-3.5 mr-1" />
                                        Thiết lập định lượng
                                    </Button>
                                </div>

                                <!-- Current recipes list of this product -->
                                <div class="bg-slate-50/50 dark:bg-slate-900/40 p-3 rounded-xl border border-slate-100 dark:border-slate-800/80">
                                    <div v-if="p.recipes.length" class="flex flex-wrap gap-2">
                                        <span
                                            v-for="r in p.recipes"
                                            :key="r.id"
                                            class="text-[10px] bg-white border dark:bg-slate-950 px-2.5 py-1.5 rounded-lg flex items-center gap-1 text-slate-700 dark:text-slate-300 font-medium"
                                        >
                                            <strong>{{ r.ingredient_name }}:</strong>
                                            <span class="font-mono text-indigo-600 dark:text-indigo-400 font-bold">{{ r.quantity }}</span>
                                            <span class="text-slate-400">{{ r.unit_symbol }}</span>
                                            <span v-if="r.waste_rate > 0" class="text-[9px] bg-amber-50 text-amber-700 px-1 rounded-sm border border-amber-200">
                                                Hao hụt: {{ r.waste_rate }}%
                                            </span>
                                        </span>
                                    </div>
                                    <div v-else class="text-[11px] text-slate-400 flex items-center gap-1">
                                        <AlertCircle class="size-3.5 text-slate-300" />
                                        Chưa có công thức nấu. Món ăn này sẽ không trừ kho khi bán!
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-slate-400 text-xs">
                            Thực đơn trống. Vui lòng hoàn thành Ngày 1 Tour để thêm món ăn trước!
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
