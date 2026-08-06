<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Package, X, Sparkles } from 'lucide-vue-next';
import { watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    isOpen: boolean;
    ingredient?: any | null;
    units: Array<{ id: number; name: string; symbol: string }>;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'success'): void;
}>();

const form = useForm({
    name: '',
    unit_id: '',
    category: '',
    storage_type: 'dry',
    default_shelf_life_days: '',
    storage_location: '',
    expiry_warning_days: '3',
    min_stock_level: '0',
    reorder_level: '0',
    auto_waste_end_of_day: false,
});

watch(
    () => [props.isOpen, props.ingredient],
    ([open, ing]) => {
        if (open) {
            if (ing) {
                form.name = ing.name || '';
                form.unit_id = ing.unit?.id ? String(ing.unit.id) : (props.units[0]?.id ? String(props.units[0].id) : '');
                form.category = ing.category_name || '';
                form.storage_type = ing.storage_type || 'dry';
                form.default_shelf_life_days = ing.default_shelf_life_days !== null && ing.default_shelf_life_days !== undefined ? String(ing.default_shelf_life_days) : '';
                form.storage_location = ing.storage_location || '';
                form.expiry_warning_days = ing.expiry_warning_days ? String(ing.expiry_warning_days) : '3';
                form.min_stock_level = ing.min_stock_level ? String(ing.min_stock_level) : '0';
                form.reorder_level = ing.reorder_level ? String(ing.reorder_level) : '0';
                form.auto_waste_end_of_day = Boolean(ing.auto_waste_end_of_day);
            } else {
                form.reset();
                form.unit_id = props.units[0]?.id ? String(props.units[0].id) : '';
                form.storage_type = 'dry';
                form.expiry_warning_days = '3';
            }
        }
    },
    { immediate: true },
);

// Preset shelf life based on storage type selection
const handleStorageTypeChange = () => {
    if (form.storage_type === 'fresh') {
        form.default_shelf_life_days = '3';
        form.expiry_warning_days = '1';
        if (!form.storage_location) form.storage_location = 'Tủ mát (2-4°C)';
    } else if (form.storage_type === 'daily') {
        form.default_shelf_life_days = '1';
        form.expiry_warning_days = '1';
        form.auto_waste_end_of_day = true;
        if (!form.storage_location) form.storage_location = 'Quầy chế biến trong ngày';
    } else if (form.storage_type === 'canned_packaged') {
        form.default_shelf_life_days = '180';
        form.expiry_warning_days = '7';
        if (!form.storage_location) form.storage_location = 'Kệ kho khô - Khu C';
    } else if (form.storage_type === 'short_shelf') {
        form.default_shelf_life_days = '10';
        form.expiry_warning_days = '2';
        if (!form.storage_location) form.storage_location = 'Tủ bảo quản mát';
    } else {
        form.default_shelf_life_days = '365';
        form.expiry_warning_days = '14';
        if (!form.storage_location) form.storage_location = 'Kho khô chính - Kệ A';
    }
};

const submitForm = () => {
    if (props.ingredient) {
        form.put(`/inventory/ingredients/${props.ingredient.id}`, {
            onSuccess: () => {
                toast.success('Đã cập nhật nguyên liệu thành công!');
                emit('success');
                emit('close');
            },
            onError: () => toast.error('Vui lòng kiểm tra lại thông tin nguyên liệu.'),
        });
    } else {
        form.post('/inventory/ingredients', {
            onSuccess: () => {
                toast.success('Đã thêm nguyên liệu mới thành công!');
                emit('success');
                emit('close');
            },
            onError: () => toast.error('Vui lòng kiểm tra lại thông tin nguyên liệu.'),
        });
    }
};
</script>

<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs overflow-y-auto"
    >
        <Card
            class="w-full max-w-xl animate-in shadow-2xl duration-150 zoom-in-95 fade-in my-8"
        >
            <CardHeader
                class="flex flex-row items-center justify-between gap-4 border-b pb-3"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-1.5 text-base font-bold text-indigo-700 dark:text-indigo-400"
                    >
                        <Package class="size-5" />
                        {{ ingredient ? 'Chỉnh Sửa Nguyên Liệu Master' : 'Thêm Nguyên Liệu & Phân Loại Bảo Quản' }}
                    </CardTitle>
                    <CardDescription
                        >Khai báo loại bảo quản (Tươi sống, Đồ khô, Bán trong ngày), thời hạn sử dụng & định mức tồn kho.</CardDescription
                    >
                </div>
                <button
                    @click="emit('close')"
                    class="cursor-pointer rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    <X class="size-4" />
                </button>
            </CardHeader>

            <CardContent class="space-y-4 pt-4">
                <form @submit.prevent="submitForm" class="space-y-4">
                    <!-- Tên & Danh mục -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Tên nguyên liệu <span class="text-rose-500">*</span></Label>
                            <Input
                                v-model="form.name"
                                placeholder="Ví dụ: Thịt bò file, Bún tươi, Nước sốt..."
                                required
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Đơn vị tính <span class="text-rose-500">*</span></Label>
                            <select
                                v-model="form.unit_id"
                                required
                                class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-semibold focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                            >
                                <option
                                    v-for="u in units"
                                    :key="u.id"
                                    :value="String(u.id)"
                                >
                                    {{ u.name }} ({{ u.symbol }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Nhóm / Phân loại ngành</Label>
                        <Input
                            v-model="form.category"
                            placeholder="Ví dụ: Thực phẩm tươi, Đồ khô, Gia vị, Đồ uống..."
                        />
                    </div>

                    <!-- Loại bảo quản & Hạn sử dụng -->
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-3.5 space-y-3 dark:border-indigo-900/40 dark:bg-indigo-950/20">
                        <div class="flex items-center gap-2 text-xs font-bold text-indigo-700 dark:text-indigo-300">
                            <Sparkles class="size-4" />
                            ĐẶC TÍNH BẢO QUẢN & HẠN SỬ DỤNG (SHELF-LIFE)
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Loại bảo quản</Label>
                                <select
                                    v-model="form.storage_type"
                                    @change="handleStorageTypeChange"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-semibold focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                >
                                    <option value="fresh">🥬 Hàng tươi sống (Hạn 1-3 ngày)</option>
                                    <option value="daily">🥖 Bán trong ngày (Hết ca / Cuối ngày)</option>
                                    <option value="short_shelf">🥛 Hạn ngắn (5-15 ngày)</option>
                                    <option value="canned_packaged">🥫 Đồ đóng hộp / Đóng gói</option>
                                    <option value="dry">🌾 Đồ khô & Gia vị (Hạn dài)</option>
                                </select>
                            </div>

                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Vị trí lưu trữ trong kho</Label>
                                <Input
                                    v-model="form.storage_location"
                                    placeholder="Ví dụ: Tủ mát Bếp 1, Tủ đông A, Kho khô Kệ 2..."
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Số ngày HSD tiêu chuẩn</Label>
                                <Input
                                    v-model="form.default_shelf_life_days"
                                    type="number"
                                    placeholder="Ví dụ: 3 (ngày)"
                                    min="1"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Báo HSD sắp hết trước</Label>
                                <div class="flex items-center gap-1.5">
                                    <Input
                                        v-model="form.expiry_warning_days"
                                        type="number"
                                        placeholder="3"
                                        min="1"
                                    />
                                    <span class="text-xs text-muted-foreground shrink-0">ngày</span>
                                </div>
                            </div>
                        </div>

                        <!-- Checkbox auto waste end of day for daily items -->
                        <div v-if="form.storage_type === 'daily'" class="flex items-center gap-2 pt-1 text-xs">
                            <input
                                id="auto_waste_chk"
                                type="checkbox"
                                v-model="form.auto_waste_end_of_day"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <Label for="auto_waste_chk" class="cursor-pointer font-medium text-amber-700 dark:text-amber-400">
                                Tự động gợi ý xuất hủy / chuyển dùng hết vào cuối ngày
                            </Label>
                        </div>
                    </div>

                    <!-- Mức tồn an toàn & Cảnh báo nhập hàng -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Tồn kho tối thiểu (Min Stock)</Label>
                            <Input
                                v-model="form.min_stock_level"
                                type="number"
                                step="any"
                                min="0"
                                placeholder="0"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Điểm đặt hàng lại (Reorder)</Label>
                            <Input
                                v-model="form.reorder_level"
                                type="number"
                                step="any"
                                min="0"
                                placeholder="0"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            @click="emit('close')"
                            class="rounded-xl"
                        >
                            Hủy bỏ
                        </Button>
                        <Button
                            type="submit"
                            class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-700"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Đang lưu...' : (ingredient ? 'Lưu cập nhật' : 'Thêm nguyên liệu') }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
