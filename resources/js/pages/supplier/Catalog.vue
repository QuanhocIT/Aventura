<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit2, Package, Tag, ShieldCheck, X } from 'lucide-vue-next';

const props = defineProps<{
    ingredients: any[];
    units: any[];
}>();

const showModal = ref(false);

const form = useForm({
    id: null as number | null,
    name: '',
    sku: '',
    price: 0,
    unit_id: props.units[0]?.id || 0,
    category_name: '',
    description: '',
    status: 'active',
});

const openAddModal = () => {
    form.reset();
    form.id = null;
    showModal.value = true;
};

const openEditModal = (item: any) => {
    form.id = item.id;
    form.name = item.name;
    form.sku = item.sku;
    form.price = item.price;
    form.unit_id = props.units.find(u => u.symbol === item.unit_symbol)?.id || props.units[0]?.id || 0;
    form.category_name = item.category_name || '';
    form.description = item.description || '';
    form.status = item.status;
    showModal.value = true;
};

const saveItem = () => {
    form.post(route('supplier.catalog.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        }
    });
};
</script>

<template>
    <Head title="Supplier Catalog Management" />

    <div class="px-6 py-8 max-w-7xl mx-auto space-y-6 text-slate-100">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">
                    Danh mục & Bảng giá Niêm yết
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    Quản lý danh sách nguyên vật liệu và niêm yết giá bán trực tiếp thời gian thực cho nhà hàng.
                </p>
            </div>
            <button 
                @click="openAddModal" 
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg font-semibold hover:from-emerald-500 hover:to-teal-400 shadow-lg shadow-emerald-950/30 transition-all active:scale-95"
            >
                <Plus class="w-5 h-5" />
                Thêm nguyên vật liệu
            </button>
        </div>

        <!-- Catalog List -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div 
                v-for="ing in ingredients" 
                :key="ing.id" 
                class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-5 hover:border-slate-700/80 transition-all flex flex-col justify-between shadow-md backdrop-blur-sm"
            >
                <div class="space-y-4">
                    <div class="flex items-start justify-between">
                        <div class="space-y-1">
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 bg-slate-950 border border-slate-850 rounded text-slate-400">
                                {{ ing.category_name || 'Vật tư chung' }}
                            </span>
                            <h3 class="text-lg font-bold text-slate-200 pt-1">{{ ing.name }}</h3>
                            <span :class="['text-[10px] px-2 py-0.5 rounded font-semibold border inline-block', ing.status === 'active' ? 'bg-emerald-950/40 text-emerald-400 border-emerald-900/50' : 'bg-rose-950/40 text-rose-400 border-rose-900/50']">
                                {{ ing.status === 'active' ? 'Đang bán' : 'Ngừng bán' }}
                            </span>
                        </div>
                        <button @click="openEditModal(ing)" class="p-1.5 text-slate-400 hover:text-amber-400 rounded-md hover:bg-slate-800 transition-colors border border-transparent">
                            <Edit2 class="w-4 h-4" />
                        </button>
                    </div>

                    <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed h-8">{{ ing.description || 'Chưa cập nhật mô tả quy cách.' }}</p>

                    <div class="flex items-center justify-between bg-slate-950/60 border border-slate-850 p-3 rounded-lg">
                        <div class="flex items-center gap-1.5 text-slate-400">
                            <Tag class="w-4 h-4 text-emerald-400" />
                            <span class="text-xs font-semibold">Giá bán niêm yết:</span>
                        </div>
                        <span class="text-md font-black text-emerald-400">
                            {{ Number(ing.price).toLocaleString('vi-VN') }}đ <span class="text-[10px] font-normal text-slate-500">/ {{ ing.unit_symbol }}</span>
                        </span>
                    </div>
                </div>

                <div class="text-[10px] text-slate-500 font-mono mt-4 pt-3 border-t border-slate-800/50 flex justify-between">
                    <span>Mã vật tư: {{ ing.sku }}</span>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="ingredients.length === 0" class="col-span-full py-16 text-center bg-slate-900/20 border border-dashed border-slate-800 rounded-2xl">
                <Package class="w-12 h-12 text-slate-600 mx-auto mb-3" />
                <p class="text-slate-400 font-medium">Chưa có nguyên vật liệu nào được niêm yết.</p>
                <button @click="openAddModal" class="mt-4 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 transition-all">Niêm yết vật tư mới</button>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-slate-950 flex items-center justify-between border-b border-slate-800">
                    <h3 class="font-bold text-lg text-slate-200">{{ form.id ? 'Cập nhật niêm yết vật tư' : 'Niêm yết nguyên vật liệu mới' }}</h3>
                    <button @click="showModal = false" class="p-1 text-slate-400 hover:text-slate-200 rounded-md hover:bg-slate-800 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="saveItem" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Tên vật tư cung ứng <span class="text-rose-500">*</span></label>
                            <input v-model="form.name" required type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" placeholder="Ví dụ: Thịt bò Wagyu cắt lát" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Mã SKU kiểm kho <span class="text-rose-500">*</span></label>
                            <input v-model="form.sku" required type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" placeholder="Ví dụ: WAGYU-BEEF-01" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Nhóm danh mục</label>
                            <input v-model="form.category_name" type="text" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" placeholder="Ví dụ: Thực phẩm tươi sống" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Đơn giá bán <span class="text-rose-500">*</span></label>
                            <input v-model="form.price" required type="number" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Đơn vị tính <span class="text-rose-500">*</span></label>
                            <select v-model="form.unit_id" required class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500">
                                <option v-for="unit in units" :key="unit.id" :value="unit.id">{{ unit.name }} ({{ unit.symbol }})</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Quy cách đóng gói & Mô tả chi tiết</label>
                            <textarea v-model="form.description" rows="3" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500" placeholder="Ví dụ: Đóng gói khay xốp hút chân không, túi 500g bảo quản mát 2-4 độ C."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Trạng thái bán</label>
                            <select v-model="form.status" class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-emerald-500">
                                <option value="active">Đang bán</option>
                                <option value="inactive">Ngừng bán</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-4 border-t border-slate-800 mt-6">
                        <button type="button" @click="showModal = false" class="px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-400 hover:text-slate-200 transition-colors">
                            Hủy bỏ
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-500 text-white rounded-lg text-sm font-semibold hover:from-emerald-500 hover:to-teal-400 transition-all">
                            {{ form.processing ? 'Đang lưu...' : 'Lưu thông tin' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</template>
