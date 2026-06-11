<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import { 
    Search, 
    Utensils, 
    ShoppingCart, 
    Plus, 
    Minus, 
    Trash2, 
    Notebook, 
    Sparkles, 
    CheckCircle2, 
    ArrowLeft, 
    ShoppingBag
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { toast } from 'vue-sonner';

interface Product {
    id: number;
    name: string;
    price: number;
    sku: string;
    description: string | null;
    category_id: number;
    category_name: string | null;
}

interface Category {
    id: number;
    name: string;
}

interface Restaurant {
    id: number;
    name: string;
}

interface Table {
    id: number;
    name: string;
    capacity: number;
}

const props = defineProps<{
    restaurant: Restaurant;
    table: Table;
    products: Product[];
    categories: Category[];
}>();

// States
const searchQuery = ref('');
const activeCategory = ref<number | null>(null);
const cart = ref<{ product: Product; quantity: number; notes: string }[]>([]);
const orderNote = ref('');
const isSubmitting = ref(false);
const showCartDrawer = ref(false);
const orderPlacedSuccess = ref(false);
const submittedOrderInfo = ref<{ order_number: string } | null>(null);

// Category filter + search logic
const filteredProducts = computed(() => {
    return props.products.filter(p => {
        const matchesCategory = activeCategory.value === null || p.category_id === activeCategory.value;
        const matchesSearch = p.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (p.description && p.description.toLowerCase().includes(searchQuery.value.toLowerCase()));
        return matchesCategory && matchesSearch;
    });
});

// Cart operations
const cartTotalItems = computed(() => {
    return cart.value.reduce((acc, item) => acc + item.quantity, 0);
});

const cartTotalPrice = computed(() => {
    return cart.value.reduce((acc, item) => acc + (item.product.price * item.quantity), 0);
});

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(value);
};

const addToCart = (product: Product) => {
    const existingIndex = cart.value.findIndex(item => item.product.id === product.id);
    if (existingIndex > -1) {
        cart.value[existingIndex].quantity++;
    } else {
        cart.value.push({
            product,
            quantity: 1,
            notes: ''
        });
    }
    toast.success(`Đã thêm ${product.name} vào giỏ hàng`);
};

const updateQuantity = (productId: number, delta: number) => {
    const index = cart.value.findIndex(item => item.product.id === productId);
    if (index > -1) {
        cart.value[index].quantity += delta;
        if (cart.value[index].quantity <= 0) {
            cart.value.splice(index, 1);
            toast.info('Đã xóa món ăn khỏi giỏ hàng');
        }
    }
};

const removeItem = (productId: number) => {
    const index = cart.value.findIndex(item => item.product.id === productId);
    if (index > -1) {
        cart.value.splice(index, 1);
        toast.info('Đã xóa món ăn khỏi giỏ hàng');
    }
};

const submitOrder = async () => {
    if (cart.value.length === 0) return;
    isSubmitting.value = true;
    
    try {
        const payload = {
            note: orderNote.value,
            items: cart.value.map(item => ({
                product_id: item.product.id,
                quantity: item.quantity,
                notes: item.notes || null
            }))
        };
        
        // Dynamically build submit URL path
        const currentPath = window.location.pathname; // Should be /order/{restaurant}/{table_token}
        const response = await axios.post(currentPath, payload);
        
        if (response.data.success) {
            orderPlacedSuccess.value = true;
            submittedOrderInfo.value = response.data.order;
            cart.value = [];
            orderNote.value = '';
            showCartDrawer.value = false;
            toast.success(response.data.message);
        }
    } catch (err: any) {
        console.error(err);
        const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi gửi đơn hàng. Vui lòng thử lại.';
        toast.error(errorMsg);
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <Head :title="`Thực đơn gọi món - ${restaurant.name}`" />

    <div class="min-h-screen bg-slate-50 text-slate-900 pb-28 dark:bg-slate-950 dark:text-slate-100 font-sans">
        <!-- Main Layout -->
        <div v-if="!orderPlacedSuccess" class="mx-auto max-w-lg bg-white min-h-screen shadow-lg relative pb-4 dark:bg-slate-900">
            
            <!-- Restaurant & Table Header -->
            <div class="relative overflow-hidden bg-gradient-to-r from-violet-600 to-indigo-700 text-white p-6 rounded-b-[2rem] shadow-md">
                <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-xl"></div>
                <div class="absolute -left-10 -bottom-10 w-32 h-32 bg-white/10 rounded-full blur-xl"></div>
                
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-md">
                        <Utensils class="size-6 text-white" />
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight leading-tight">{{ restaurant.name }}</h1>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="inline-block px-2.5 py-0.5 bg-white/25 backdrop-blur-sm text-xs font-semibold rounded-full">
                                Bàn: {{ table.name }}
                            </span>
                            <span class="text-xs text-white/80">
                                Sức chứa: {{ table.capacity }} người
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="px-4 mt-6 space-y-4">
                <div class="relative">
                    <Search class="absolute left-3 top-3 size-4 text-slate-400" />
                    <Input 
                        v-model="searchQuery" 
                        placeholder="Tìm kiếm món ăn ngon hôm nay..." 
                        class="pl-9 h-11 bg-slate-50 border-slate-200/80 rounded-xl focus-visible:ring-violet-500 dark:bg-slate-950 dark:border-slate-800"
                    />
                </div>

                <!-- Horizontal categories -->
                <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-none">
                    <button 
                        @click="activeCategory = null"
                        class="shrink-0 px-4 py-2 text-xs font-semibold rounded-full transition-all border"
                        :class="activeCategory === null 
                            ? 'bg-violet-600 text-white border-violet-600 shadow-sm shadow-violet-500/20' 
                            : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                    >
                        Tất cả
                    </button>
                    <button 
                        v-for="cat in categories" 
                        :key="cat.id"
                        @click="activeCategory = cat.id"
                        class="shrink-0 px-4 py-2 text-xs font-semibold rounded-full transition-all border"
                        :class="activeCategory === cat.id 
                            ? 'bg-violet-600 text-white border-violet-600 shadow-sm shadow-violet-500/20' 
                            : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700'"
                    >
                        {{ cat.name }}
                    </button>
                </div>
            </div>

            <!-- Products List -->
            <div class="px-4 mt-4 space-y-3">
                <div v-if="filteredProducts.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400">
                    <ShoppingBag class="size-12 stroke-[1.5] mb-2 text-slate-300" />
                    <p class="text-sm">Không tìm thấy món ăn nào</p>
                </div>

                <Card 
                    v-for="product in filteredProducts" 
                    :key="product.id"
                    class="overflow-hidden border-slate-100 hover:shadow-md transition-shadow duration-300 dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent class="p-4 flex gap-4">
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-slate-800 dark:text-slate-100 text-base leading-snug">{{ product.name }}</h3>
                            <p class="text-xs text-slate-400 mt-0.5">{{ product.category_name }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 line-clamp-2 leading-relaxed">
                                {{ product.description || 'Chưa có mô tả chi tiết cho món ăn này.' }}
                            </p>
                            <p class="text-violet-600 font-bold font-mono mt-2.5 dark:text-violet-400 text-base">
                                {{ formatCurrency(product.price) }}
                            </p>
                        </div>
                        
                        <div class="flex flex-col justify-end">
                            <Button 
                                size="sm" 
                                @click="addToCart(product)"
                                class="rounded-xl h-9 px-3 bg-violet-600 hover:bg-violet-700 text-white shadow-sm hover:scale-105 transition-transform"
                            >
                                <Plus class="size-4 mr-1" /> Thêm
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Floating Cart Indicator -->
            <div 
                v-if="cart.length > 0"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 w-[90%] max-w-md bg-slate-900/90 text-white backdrop-blur-md p-4 rounded-2xl flex items-center justify-between shadow-2xl z-40 border border-white/10 dark:bg-slate-950/95"
            >
                <div class="flex items-center gap-3">
                    <div class="relative bg-violet-600 p-2.5 rounded-xl animate-bounce">
                        <ShoppingCart class="size-5" />
                        <span class="absolute -top-1.5 -right-1.5 bg-rose-500 text-white text-[10px] font-bold h-5 w-5 rounded-full flex items-center justify-center border border-slate-900">
                            {{ cartTotalItems }}
                        </span>
                    </div>
                    <div>
                        <p class="text-[10px] text-white/60 font-semibold tracking-wide uppercase">Tạm tính</p>
                        <p class="text-base font-bold font-mono">{{ formatCurrency(cartTotalPrice) }}</p>
                    </div>
                </div>
                
                <Button 
                    @click="showCartDrawer = true"
                    class="bg-white text-slate-950 hover:bg-slate-100 font-bold rounded-xl px-5 h-10 shadow-sm"
                >
                    Xem giỏ hàng
                </Button>
            </div>
            
            <!-- Cart Drawer Backdrop -->
            <div 
                v-if="showCartDrawer" 
                @click="showCartDrawer = false"
                class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 transition-opacity"
            ></div>

            <!-- Cart Drawer Sheet -->
            <div 
                class="fixed bottom-0 left-0 right-0 max-w-lg mx-auto bg-white rounded-t-[2rem] shadow-2xl z-50 transform transition-transform duration-300 dark:bg-slate-900 overflow-y-auto max-h-[85vh] flex flex-col"
                :class="showCartDrawer ? 'translate-y-0' : 'translate-y-full'"
            >
                <!-- Drawer Header -->
                <div class="flex items-center justify-between p-6 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <ShoppingCart class="size-5 text-violet-600" />
                        <h2 class="text-lg font-bold">Chi tiết giỏ hàng</h2>
                    </div>
                    <button @click="showCartDrawer = false" class="p-1.5 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800">
                        <Minus class="size-5" />
                    </button>
                </div>

                <!-- Drawer Content -->
                <div class="p-6 overflow-y-auto flex-1 space-y-4">
                    <div class="space-y-3.5">
                        <div 
                            v-for="item in cart" 
                            :key="item.product.id"
                            class="flex flex-col gap-2 p-3 bg-slate-50 rounded-xl border border-slate-100 dark:bg-slate-950 dark:border-slate-850"
                        >
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-bold text-slate-800 dark:text-slate-100 text-sm leading-tight">{{ item.product.name }}</h4>
                                    <p class="text-xs text-slate-500 mt-0.5 font-mono">{{ formatCurrency(item.product.price) }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button 
                                        @click="updateQuantity(item.product.id, -1)"
                                        class="h-7 w-7 bg-white dark:bg-slate-800 rounded-lg flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-700 shadow-sm border"
                                    >
                                        <Minus class="size-3.5" />
                                    </button>
                                    <span class="font-mono font-bold text-sm w-6 text-center">{{ item.quantity }}</span>
                                    <button 
                                        @click="updateQuantity(item.product.id, 1)"
                                        class="h-7 w-7 bg-white dark:bg-slate-800 rounded-lg flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-700 shadow-sm border"
                                    >
                                        <Plus class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Notes for kitchen -->
                            <div class="flex items-center gap-2 mt-1.5">
                                <Notebook class="size-3.5 text-slate-400 shrink-0" />
                                <input 
                                    v-model="item.notes"
                                    placeholder="Ghi chú món (Ví dụ: Ít cay, không hành...)"
                                    class="w-full text-xs bg-transparent border-b border-transparent hover:border-slate-200 focus:border-violet-500 focus:outline-none py-0.5 dark:hover:border-slate-800"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- General Note -->
                    <div class="space-y-1.5 pt-2">
                        <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Ghi chú cho nhà bếp (chung)</label>
                        <Input 
                            v-model="orderNote" 
                            placeholder="Ví dụ: Giao các món nước trước..."
                            class="bg-slate-50 border-slate-200 dark:bg-slate-950 dark:border-slate-800"
                        />
                    </div>

                    <!-- Total box -->
                    <div class="p-4 bg-violet-50 rounded-xl flex items-center justify-between dark:bg-violet-950/20">
                        <div>
                            <p class="text-xs text-violet-600/70 font-semibold dark:text-violet-400/70">TỔNG HÓA ĐƠN</p>
                            <p class="text-lg font-bold font-mono text-violet-700 dark:text-violet-400">{{ formatCurrency(cartTotalPrice) }}</p>
                        </div>
                        <div class="text-xs text-slate-500 text-right dark:text-slate-400">
                            {{ cartTotalItems }} phần ăn
                        </div>
                    </div>
                </div>

                <!-- Drawer Footer -->
                <div class="p-6 border-t border-slate-100 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900/50">
                    <Button 
                        @click="submitOrder"
                        :disabled="isSubmitting"
                        class="w-full bg-violet-600 hover:bg-violet-700 text-white font-bold h-12 rounded-xl text-base shadow-md transition-all flex items-center justify-center gap-2"
                    >
                        <span v-if="isSubmitting" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                        <Sparkles v-else class="size-5" />
                        {{ isSubmitting ? 'Đang gửi yêu cầu...' : 'Gửi yêu cầu gọi món' }}
                    </Button>
                </div>
            </div>

        </div>

        <!-- Success Screen -->
        <div v-else class="mx-auto max-w-lg bg-white min-h-screen shadow-lg flex flex-col justify-between p-6 dark:bg-slate-900">
            <div></div> <!-- Spacer -->

            <div class="flex flex-col items-center justify-center text-center space-y-6">
                <div class="h-20 w-20 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-500 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/30 animate-pulse">
                    <CheckCircle2 class="size-12 stroke-[1.5]" />
                </div>
                
                <div class="space-y-2">
                    <h2 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">Gửi đơn hàng thành công!</h2>
                    <p class="text-slate-500 dark:text-slate-400 text-sm max-w-sm leading-relaxed">
                        Đơn đệm <strong class="font-mono text-slate-700 dark:text-slate-300 font-bold bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">{{ submittedOrderInfo?.order_number }}</strong> đã được gửi tới hệ thống.
                    </p>
                    <p class="text-sm font-semibold text-violet-600 dark:text-violet-400 mt-2">
                        Nhân viên sẽ di chuyển ra bàn để đối chiếu trực tiếp.
                    </p>
                </div>

                <div class="w-full bg-slate-50 rounded-2xl p-5 border border-slate-100 dark:bg-slate-950 dark:border-slate-850 max-w-xs">
                    <p class="text-xs text-slate-400 font-medium">BÀN ĂN CỦA BẠN</p>
                    <p class="text-2xl font-bold text-slate-800 dark:text-slate-100 mt-1">{{ table.name }}</p>
                    <div class="h-px bg-slate-200/60 dark:bg-slate-850 my-3"></div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Bạn có thể gọi thêm các món ăn khác bằng cách quét lại mã QR tại bàn bất kỳ lúc nào.
                    </p>
                </div>
            </div>

            <div class="w-full">
                <Button 
                    @click="orderPlacedSuccess = false" 
                    variant="outline"
                    class="w-full h-12 rounded-xl text-slate-600 border-slate-200 dark:border-slate-800 dark:text-slate-400"
                >
                    <ArrowLeft class="size-4 mr-2" /> Quay lại thực đơn
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Remove scrollbars */
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
