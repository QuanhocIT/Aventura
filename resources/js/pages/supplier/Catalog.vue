<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Plus,
    Edit2,
    Package,
    Tag,
    X,
    Clock,
} from 'lucide-vue-next';
import { ref } from 'vue';
import BackButton from '@/components/BackButton.vue';
import { store } from '@/routes/supplier/catalog';

const props = defineProps<{
    supplier: any;
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
    form.unit_id =
        props.units.find((u) => u.symbol === item.unit_symbol)?.id ||
        props.units[0]?.id ||
        0;
    form.category_name = item.category_name || '';
    form.description = item.description || '';
    form.status = item.status;
    showModal.value = true;
};

const saveItem = () => {
    form.post(store.url(), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
};

const showAnalyticsModal = ref(false);
const loadingAnalytics = ref(false);
const selectedIngredient = ref<any>(null);
const analyticsData = ref<any>(null);

const openAnalyticsModal = async (item: any) => {
    selectedIngredient.value = item;
    showAnalyticsModal.value = true;
    loadingAnalytics.value = true;
    analyticsData.value = null;

    try {
        const response = await axios.get(
            `/suppliers/${props.supplier.id}/ingredients/${item.id}/price-analytics`,
        );
        analyticsData.value = response.data;
    } catch (err) {
        console.error(err);
    } finally {
        loadingAnalytics.value = false;
    }
};
</script>

<template>
    <Head title="Supplier Catalog Management" />

    <div class="mx-auto max-w-7xl space-y-6 px-6 py-8 text-slate-100">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b border-slate-800 pb-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <BackButton fallback-href="/supplier/dashboard" label="Trang chủ" class="mb-3" />
                <h1
                    class="bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-3xl font-extrabold tracking-tight text-transparent"
                >
                    Danh mục & Bảng giá Niêm yết
                </h1>
                <p class="mt-1 text-sm text-slate-400">
                    Quản lý danh sách nguyên vật liệu và niêm yết giá bán trực
                    tiếp thời gian thực cho nhà hàng.
                </p>
            </div>
            <button
                @click="openAddModal"
                class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-emerald-600 to-teal-500 px-4 py-2.5 font-semibold text-white shadow-lg shadow-emerald-950/30 transition-all hover:from-emerald-500 hover:to-teal-400 active:scale-95"
            >
                <Plus class="h-5 w-5" />
                Thêm nguyên vật liệu
            </button>
        </div>

        <!-- Catalog List -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="ing in ingredients"
                :key="ing.id"
                class="flex flex-col justify-between rounded-xl border border-slate-800/80 bg-slate-900/60 p-5 shadow-md backdrop-blur-sm transition-all hover:border-slate-700/80"
            >
                <div class="space-y-4">
                    <div class="flex items-start justify-between">
                        <div class="space-y-1">
                            <span
                                class="border-slate-850 rounded border bg-slate-950 px-2 py-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                {{ ing.category_name || 'Vật tư chung' }}
                            </span>
                            <h3 class="pt-1 text-lg font-bold text-slate-200">
                                {{ ing.name }}
                            </h3>
                            <span
                                :class="[
                                    'inline-block rounded border px-2 py-0.5 text-[10px] font-semibold',
                                    ing.status === 'active'
                                        ? 'border-emerald-900/50 bg-emerald-950/40 text-emerald-400'
                                        : 'border-rose-900/50 bg-rose-950/40 text-rose-400',
                                ]"
                            >
                                {{
                                    ing.status === 'active'
                                        ? 'Đang bán'
                                        : 'Ngừng bán'
                                }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button
                                @click="openAnalyticsModal(ing)"
                                class="rounded-md border border-transparent p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-indigo-400"
                                title="Xem lịch sử giá & Phân tích AI"
                            >
                                <Clock class="h-4 w-4" />
                            </button>
                            <button
                                @click="openEditModal(ing)"
                                class="rounded-md border border-transparent p-1.5 text-slate-400 transition-colors hover:bg-slate-800 hover:text-amber-400"
                            >
                                <Edit2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <p
                        class="line-clamp-2 h-8 text-xs leading-relaxed text-slate-400"
                    >
                        {{ ing.description || 'Chưa cập nhật mô tả quy cách.' }}
                    </p>

                    <div
                        class="border-slate-850 flex items-center justify-between rounded-lg border bg-slate-950/60 p-3"
                    >
                        <div class="flex items-center gap-1.5 text-slate-400">
                            <Tag class="h-4 w-4 text-emerald-400" />
                            <span class="text-xs font-semibold"
                                >Giá bán niêm yết:</span
                            >
                        </div>
                        <span class="text-md font-black text-emerald-400">
                            {{ Number(ing.price).toLocaleString('vi-VN') }}đ
                            <span class="text-[10px] font-normal text-slate-500"
                                >/ {{ ing.unit_symbol }}</span
                            >
                        </span>
                    </div>
                </div>

                <div
                    class="mt-4 flex justify-between border-t border-slate-800/50 pt-3 font-mono text-[10px] text-slate-500"
                >
                    <span>Mã vật tư: {{ ing.sku }}</span>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="ingredients.length === 0"
                class="col-span-full rounded-2xl border border-dashed border-slate-800 bg-slate-900/20 py-16 text-center"
            >
                <Package class="mx-auto mb-3 h-12 w-12 text-slate-600" />
                <p class="font-medium text-slate-400">
                    Chưa có nguyên vật liệu nào được niêm yết.
                </p>
                <button
                    @click="openAddModal"
                    class="mt-4 rounded-lg bg-emerald-600 px-4 py-2 text-white transition-all hover:bg-emerald-500"
                >
                    Niêm yết vật tư mới
                </button>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
        >
            <div
                class="w-full max-w-lg animate-in overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-800 bg-slate-950 px-6 py-4"
                >
                    <h3 class="text-lg font-bold text-slate-200">
                        {{
                            form.id
                                ? 'Cập nhật niêm yết vật tư'
                                : 'Niêm yết nguyên vật liệu mới'
                        }}
                    </h3>
                    <button
                        @click="showModal = false"
                        class="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-200"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="saveItem" class="space-y-4 p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Tên vật tư cung ứng
                                <span class="text-rose-500">*</span></label
                            >
                            <input
                                v-model="form.name"
                                required
                                type="text"
                                class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                                placeholder="Ví dụ: Thịt bò Wagyu cắt lát"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Mã SKU kiểm kho
                                <span class="text-rose-500">*</span></label
                            >
                            <input
                                v-model="form.sku"
                                required
                                type="text"
                                class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                                placeholder="Ví dụ: WAGYU-BEEF-01"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Nhóm danh mục</label
                            >
                            <input
                                v-model="form.category_name"
                                type="text"
                                class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                                placeholder="Ví dụ: Thực phẩm tươi sống"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Đơn giá bán
                                <span class="text-rose-500">*</span></label
                            >
                            <input
                                v-model="form.price"
                                required
                                type="number"
                                class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                            />
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Đơn vị tính
                                <span class="text-rose-500">*</span></label
                            >
                            <select
                                v-model="form.unit_id"
                                required
                                class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                            >
                                <option
                                    v-for="unit in units"
                                    :key="unit.id"
                                    :value="unit.id"
                                >
                                    {{ unit.name }} ({{ unit.symbol }})
                                </option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Quy cách đóng gói & Mô tả chi tiết</label
                            >
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                                placeholder="Ví dụ: Đóng gói khay xốp hút chân không, túi 500g bảo quản mát 2-4 độ C."
                            ></textarea>
                        </div>
                        <div>
                            <label
                                class="mb-1.5 block text-xs font-semibold tracking-wider text-slate-400 uppercase"
                                >Trạng thái bán</label
                            >
                            <select
                                v-model="form.status"
                                class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-sm text-slate-200 focus:border-emerald-500 focus:outline-none"
                            >
                                <option value="active">Đang bán</option>
                                <option value="inactive">Ngừng bán</option>
                            </select>
                        </div>
                    </div>

                    <div
                        class="mt-6 flex justify-end gap-2 border-t border-slate-800 pt-4"
                    >
                        <button
                            type="button"
                            @click="showModal = false"
                            class="rounded-lg border border-slate-800 px-4 py-2 text-sm font-semibold text-slate-400 transition-colors hover:text-slate-200"
                        >
                            Hủy bỏ
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-gradient-to-r from-emerald-600 to-teal-500 px-4 py-2 text-sm font-semibold text-white transition-all hover:from-emerald-500 hover:to-teal-400"
                        >
                            {{
                                form.processing
                                    ? 'Đang lưu...'
                                    : 'Lưu thông tin'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Price History & AI Analytics Modal -->
        <div
            v-if="showAnalyticsModal && selectedIngredient"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="showAnalyticsModal = false"
        >
            <div
                class="w-full max-w-lg animate-in overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 text-left text-slate-100 shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-800 bg-slate-950 px-6 py-4"
                >
                    <div>
                        <h3 class="text-base font-bold text-slate-200">
                            Phân tích giá & Biến động AI
                        </h3>
                        <p class="text-[10px] text-slate-400">
                            Vật tư: {{ selectedIngredient.name }} ({{
                                selectedIngredient.sku
                            }})
                        </p>
                    </div>
                    <button
                        @click="showAnalyticsModal = false"
                        class="rounded-md p-1 text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-200"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="space-y-5 p-6">
                    <div
                        v-if="loadingAnalytics"
                        class="flex flex-col items-center space-y-2 py-10"
                    >
                        <div
                            class="h-8 w-8 animate-spin rounded-full border-4 border-emerald-500 border-t-transparent"
                        ></div>
                        <p class="text-xs text-slate-400">
                            Đang phân tích dữ liệu lịch sử giá bằng AI...
                        </p>
                    </div>

                    <div v-else-if="analyticsData" class="space-y-4">
                        <!-- AI recommendation -->
                        <div
                            class="space-y-1 rounded-xl border border-violet-500/20 bg-violet-500/10 p-4"
                        >
                            <h4
                                class="flex items-center gap-1.5 text-xs font-black tracking-wide text-violet-400 uppercase"
                            >
                                <span>🧠</span> Đề xuất AI dự báo giá
                            </h4>
                            <p class="text-xs leading-relaxed text-slate-200">
                                {{ analyticsData.recommendation }}
                            </p>
                        </div>

                        <!-- Volatility metrics cards -->
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                class="space-y-1 rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-center"
                            >
                                <span
                                    class="text-[9px] font-bold text-slate-400 uppercase"
                                    >Xu hướng giá</span
                                >
                                <div
                                    class="flex items-center justify-center gap-1.5 pt-1"
                                >
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase"
                                        :class="{
                                            'border border-rose-900/50 bg-rose-950 text-rose-400':
                                                analyticsData.trend ===
                                                'rising',
                                            'border border-emerald-900/50 bg-emerald-950 text-emerald-400':
                                                analyticsData.trend ===
                                                'falling',
                                            'text-slate-350 bg-slate-800':
                                                analyticsData.trend ===
                                                'stable',
                                        }"
                                    >
                                        {{
                                            analyticsData.trend === 'rising'
                                                ? 'Tăng trưởng'
                                                : analyticsData.trend ===
                                                    'falling'
                                                  ? 'Giảm giá'
                                                  : 'Ổn định'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="space-y-1 rounded-xl border border-slate-800 bg-slate-950/40 p-4 text-center"
                            >
                                <span
                                    class="text-[9px] font-bold text-slate-400 uppercase"
                                    >Tỷ lệ thay đổi</span
                                >
                                <p
                                    class="pt-0.5 font-mono text-base font-black"
                                    :class="
                                        analyticsData.percentage_change >= 0
                                            ? 'text-rose-400'
                                            : 'text-emerald-400'
                                    "
                                >
                                    {{
                                        analyticsData.percentage_change >= 0
                                            ? '+'
                                            : ''
                                    }}{{ analyticsData.percentage_change }}%
                                </p>
                            </div>
                        </div>

                        <!-- Price history averages or points table -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-bold text-slate-300">
                                Nhật ký điều chỉnh giá gần đây
                            </h4>
                            <div
                                class="border-slate-850 max-h-40 overflow-y-auto rounded-xl border bg-slate-950/20"
                            >
                                <table class="w-full text-left text-xs">
                                    <thead
                                        class="sticky top-0 bg-slate-900 text-[10px] font-bold text-slate-400 uppercase"
                                    >
                                        <tr>
                                            <th class="px-4 py-2">
                                                Ngày áp dụng
                                            </th>
                                            <th class="px-4 py-2 text-right">
                                                Đơn giá niêm yết
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/40">
                                        <tr
                                            v-for="(
                                                historyItem, idx
                                            ) in analyticsData.monthly_averages ||
                                            []"
                                            :key="idx"
                                            class="hover:bg-slate-800/20"
                                        >
                                            <td
                                                class="px-4 py-2.5 font-medium text-slate-300"
                                            >
                                                {{
                                                    historyItem.month ||
                                                    historyItem.date ||
                                                    'Tháng ' + (Number(idx) + 1)
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-2.5 text-right font-mono font-bold text-emerald-400"
                                            >
                                                {{
                                                    (
                                                        historyItem.average_price ||
                                                        historyItem.price ||
                                                        0
                                                    ).toLocaleString('vi-VN')
                                                }}đ
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                !(
                                                    analyticsData.monthly_averages ||
                                                    []
                                                ).length
                                            "
                                        >
                                            <td
                                                colspan="2"
                                                class="px-4 py-6 text-center text-slate-500"
                                            >
                                                Chưa ghi nhận sự thay đổi giá
                                                nào khác.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-end border-t border-slate-800 bg-slate-950 px-6 py-4"
                >
                    <button
                        type="button"
                        @click="showAnalyticsModal = false"
                        class="text-slate-350 cursor-pointer rounded-xl border border-slate-800 bg-slate-900 px-4 py-2 text-xs font-bold transition-all hover:bg-slate-800 hover:text-white"
                    >
                        Đóng lại
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
