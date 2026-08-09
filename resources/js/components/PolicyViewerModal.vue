<script setup lang="ts">
import axios from 'axios';
import { BookOpen, FileText, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    isOpen: boolean;
}>();

const emit = defineEmits(['update:isOpen']);

const isLoading = ref(false);
const searchQuery = ref('');
const selectedCategory = ref<string>('all');
const policies = ref<Array<any>>([]);
const selectedPolicy = ref<any>(null);

const fetchPolicies = async () => {
    isLoading.value = true;

    try {
        const res = await axios.get('/api/company-policies');

        if (res.data.success) {
            policies.value = res.data.data || [];
        }
    } catch {
        toast.error('Không thể tải Bộ quy định tiêu chuẩn.');
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.isOpen,
    (val) => {
        if (val) {
            fetchPolicies();
        }
    },
);

const filteredPolicies = computed(() => {
    return policies.value.filter((p) => {
        const matchesCategory = selectedCategory.value === 'all' || p.category === selectedCategory.value;
        const matchesSearch =
            p.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            p.policy_code.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            p.content.toLowerCase().includes(searchQuery.value.toLowerCase());

        return matchesCategory && matchesSearch;
    });
});

const getCategoryLabel = (cat: string) => {
    switch (cat) {
        case 'food_safety':
            return 'An Toàn Thực Phẩm';
        case 'service_attitude':
            return 'Thái Độ Phục Vụ';
        case 'inventory_storage':
            return 'Vệ Sinh Kho & Bảo Quản';
        case 'pos_cashier':
            return 'POS & Thu Ngân';
        case 'uniform_time':
            return 'Đồng Phục & Giờ Giấc';
        default:
            return 'Quy Định Chung';
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
};

const closeModal = () => {
    emit('update:isOpen', false);
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full h-[85vh] flex flex-col overflow-hidden border border-slate-200">
            <!-- Header -->
            <div class="p-5 border-b bg-gradient-to-r from-indigo-950 via-slate-900 to-slate-950 text-white flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-indigo-500/20 rounded-xl border border-indigo-400/30">
                        <BookOpen class="w-6 h-6 text-indigo-400" />
                    </div>
                    <div>
                        <h3 class="font-bold text-lg">Bộ Quy Định & Tiêu Chuẩn Vận Hành Nhà Hàng</h3>
                        <p class="text-xs text-indigo-200/80">Tra cứu nhanh các quy tắc, tiêu chuẩn chất lượng và chế tài thưởng phạt từ Ban Quản Trị</p>
                    </div>
                </div>
                <button @click="closeModal" class="text-slate-400 hover:text-white p-1.5 rounded-lg transition">
                    <X class="w-6 h-6" />
                </button>
            </div>

            <!-- Content Area -->
            <div class="flex-1 flex overflow-hidden">
                <!-- Sidebar Filter & List -->
                <div class="w-1/3 border-r bg-slate-50 flex flex-col p-4 space-y-3">
                    <div class="relative">
                        <Search class="w-4 h-4 absolute left-3 top-3 text-slate-400" />
                        <Input v-model="searchQuery" placeholder="Tìm kiếm quy định..." class="pl-9 text-xs bg-white" />
                    </div>

                    <div class="flex flex-wrap gap-1">
                        <button
                            @click="selectedCategory = 'all'"
                            :class="['px-2.5 py-1 rounded-lg text-[11px] font-semibold transition', selectedCategory === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300']"
                        >
                            Tất cả
                        </button>
                        <button
                            @click="selectedCategory = 'food_safety'"
                            :class="['px-2.5 py-1 rounded-lg text-[11px] font-semibold transition', selectedCategory === 'food_safety' ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300']"
                        >
                            ATTP
                        </button>
                        <button
                            @click="selectedCategory = 'service_attitude'"
                            :class="['px-2.5 py-1 rounded-lg text-[11px] font-semibold transition', selectedCategory === 'service_attitude' ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300']"
                        >
                            Phục vụ
                        </button>
                        <button
                            @click="selectedCategory = 'pos_cashier'"
                            :class="['px-2.5 py-1 rounded-lg text-[11px] font-semibold transition', selectedCategory === 'pos_cashier' ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700 hover:bg-slate-300']"
                        >
                            Thu Ngân
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto space-y-2 pr-1">
                        <div v-if="isLoading" class="p-8 text-center text-xs text-slate-400">
                            Đang tải quy định...
                        </div>
                        <div v-else-if="filteredPolicies.length === 0" class="p-8 text-center text-xs text-slate-400">
                            Không tìm thấy quy định phù hợp.
                        </div>
                        <div
                            v-for="p in filteredPolicies"
                            :key="p.id"
                            @click="selectedPolicy = p"
                            :class="['p-3 rounded-xl border text-left cursor-pointer transition', selectedPolicy?.id === p.id ? 'bg-white border-indigo-500 shadow-sm' : 'bg-white/60 border-slate-200 hover:bg-white']"
                        >
                            <div class="flex justify-between items-start gap-1">
                                <span class="font-mono text-[10px] font-bold text-indigo-600">{{ p.policy_code }}</span>
                                <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 text-[10px] font-semibold rounded">
                                    {{ getCategoryLabel(p.category) }}
                                </span>
                            </div>
                            <h4 class="font-bold text-xs text-slate-900 mt-1 line-clamp-1">{{ p.title }}</h4>
                        </div>
                    </div>
                </div>

                <!-- Main Content View -->
                <div class="w-2/3 p-6 overflow-y-auto bg-white flex flex-col justify-between">
                    <div v-if="selectedPolicy" class="space-y-4 text-xs">
                        <div class="border-b pb-4">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-mono font-bold text-indigo-600 text-sm">{{ selectedPolicy.policy_code }}</span>
                                <span class="px-2.5 py-0.5 bg-indigo-100 text-indigo-800 font-semibold rounded-md text-xs">
                                    {{ getCategoryLabel(selectedPolicy.category) }}
                                </span>
                            </div>
                            <h2 class="text-xl font-bold text-slate-900">{{ selectedPolicy.title }}</h2>
                            <p class="text-slate-500 mt-1">
                                Phạm vi:
                                <strong class="text-slate-800">
                                    {{ selectedPolicy.applies_to_all_branches ? 'Tất cả chi nhánh toàn chuỗi' : 'Áp dụng chi nhánh cụ thể' }}
                                </strong>
                            </p>
                        </div>

                        <div class="prose prose-xs max-w-none text-slate-800 space-y-3 whitespace-pre-line leading-relaxed">
                            {{ selectedPolicy.content }}
                        </div>

                        <div v-if="selectedPolicy.suggested_fine_amount > 0" class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800 flex items-center justify-between">
                            <span class="font-semibold">Mức phạt vi phạm tham chiếu định mức:</span>
                            <span class="font-bold text-sm font-mono text-rose-900">{{ formatCurrency(selectedPolicy.suggested_fine_amount) }}</span>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center h-full text-slate-400 space-y-2">
                        <FileText class="w-12 h-12 text-slate-300" />
                        <p>Chọn một quy định từ danh sách bên trái để đọc nội dung chi tiết.</p>
                    </div>

                    <div class="pt-4 border-t flex justify-end">
                        <Button @click="closeModal" variant="outline" size="sm" class="text-xs">Đóng</Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
