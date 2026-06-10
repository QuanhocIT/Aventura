<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    BadgePercent, DollarSign, Hash, Plus, Search,
    ToggleLeft, ToggleRight, Trash2, TrendingUp, Pencil, X, Check,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Coupon {
    id: number;
    code: string;
    description: string | null;
    discount_type: 'percent' | 'fixed';
    discount_value: number;
    max_uses: number | null;
    uses_count: number;
    total_discount_given: string;
    starts_at: string | null;
    expires_at: string | null;
    status: 'active' | 'inactive';
    is_valid: boolean;
}

interface Stats {
    total: number;
    active: number;
    expired: number;
    total_uses: number;
    total_saved: string;
}

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    coupons: Paginator<Coupon>;
    stats: Stats;
    filters: { status?: string; search?: string };
}>();

// Filters
const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? 'all');

let timer: ReturnType<typeof setTimeout> | undefined;
function applyFilters() {
    router.get('/super-admin/coupons', {
        search: search.value || undefined,
        status: statusFilter.value === 'all' ? undefined : statusFilter.value,
    }, { preserveState: true, replace: true });
}

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 350);
});

function goToPage(url: string | null) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

// Form state
const showForm = ref(false);
const editingCoupon = ref<Coupon | null>(null);

const formData = ref({
    code: '',
    description: '',
    discount_type: 'percent' as 'percent' | 'fixed',
    discount_value: 10,
    max_uses: '' as string | number,
    starts_at: '',
    expires_at: '',
});

function openCreateForm() {
    editingCoupon.value = null;
    formData.value = { code: '', description: '', discount_type: 'percent', discount_value: 10, max_uses: '', starts_at: '', expires_at: '' };
    showForm.value = true;
}

function openEditForm(coupon: Coupon) {
    editingCoupon.value = coupon;
    formData.value = {
        code: coupon.code,
        description: coupon.description ?? '',
        discount_type: coupon.discount_type,
        discount_value: coupon.discount_value,
        max_uses: coupon.max_uses ?? '',
        starts_at: coupon.starts_at ?? '',
        expires_at: coupon.expires_at ?? '',
    };
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
    editingCoupon.value = null;
}

function submitForm() {
    const data = {
        ...formData.value,
        max_uses: formData.value.max_uses === '' ? null : Number(formData.value.max_uses),
        starts_at: formData.value.starts_at || null,
        expires_at: formData.value.expires_at || null,
    };

    if (editingCoupon.value) {
        router.patch(`/super-admin/coupons/${editingCoupon.value.id}`, data, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Đã cập nhật coupon!'); closeForm(); },
            onError: (e) => toast.error(Object.values(e)[0] as string),
        });
    } else {
        router.post('/super-admin/coupons', data, {
            preserveScroll: true,
            onSuccess: () => { toast.success('Đã tạo coupon!'); closeForm(); },
            onError: (e) => toast.error(Object.values(e)[0] as string),
        });
    }
}

function toggleCoupon(coupon: Coupon) {
    router.patch(`/super-admin/coupons/${coupon.id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success(coupon.status === 'active' ? 'Đã vô hiệu hoá' : 'Đã kích hoạt'),
    });
}

function deleteCoupon(coupon: Coupon) {
    if (!confirm(`Xóa coupon "${coupon.code}"? Nếu đã được dùng sẽ chỉ vô hiệu hoá.`)) return;
    router.delete(`/super-admin/coupons/${coupon.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã xóa coupon!'),
    });
}

const discountLabel = computed(() =>
    formData.value.discount_type === 'percent' ? '%' : 'VND'
);
</script>

<template>
    <Head title="Quản lý Coupon" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">🎟️ Quản lý Coupon</h1>
                <p class="text-sm text-muted-foreground">Tạo và quản lý mã giảm giá cho toàn hệ thống.</p>
            </div>
            <Button @click="openCreateForm">
                <Plus class="mr-2 size-4" /> Tạo coupon
            </Button>
        </div>

        <!-- Stats -->
        <div class="grid gap-4 md:grid-cols-5">
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs text-muted-foreground">Tổng coupon</p>
                        <p class="text-2xl font-bold">{{ stats.total }}</p>
                    </div>
                    <Hash class="size-7 text-slate-400" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs text-muted-foreground">Đang hoạt động</p>
                        <p class="text-2xl font-bold text-emerald-600">{{ stats.active }}</p>
                    </div>
                    <BadgePercent class="size-7 text-emerald-400" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs text-muted-foreground">Đã hết hạn/tắt</p>
                        <p class="text-2xl font-bold text-rose-500">{{ stats.expired }}</p>
                    </div>
                    <X class="size-7 text-rose-300" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs text-muted-foreground">Tổng lần dùng</p>
                        <p class="text-2xl font-bold">{{ stats.total_uses }}</p>
                    </div>
                    <TrendingUp class="size-7 text-sky-400" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-xs text-muted-foreground">Tổng đã giảm</p>
                        <p class="text-lg font-bold text-violet-600">{{ stats.total_saved }}₫</p>
                    </div>
                    <DollarSign class="size-7 text-violet-400" />
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardContent class="flex flex-wrap gap-4 p-4">
                <div class="relative flex-1 min-w-52">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Tìm mã coupon, mô tả..." class="pl-9" />
                </div>
                <Select v-model="statusFilter" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-44">
                        <SelectValue placeholder="Trạng thái" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Tất cả</SelectItem>
                        <SelectItem value="active">Đang hoạt động</SelectItem>
                        <SelectItem value="inactive">Đã vô hiệu hoá</SelectItem>
                    </SelectContent>
                </Select>
            </CardContent>
        </Card>

        <!-- Coupon table -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle>Danh sách coupon <span class="text-sm font-normal text-muted-foreground">({{ coupons.total }} coupon)</span></CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="pb-3 text-left font-medium">Mã coupon</th>
                                <th class="pb-3 text-left font-medium">Loại</th>
                                <th class="pb-3 text-left font-medium">Giá trị</th>
                                <th class="pb-3 text-left font-medium">Sử dụng</th>
                                <th class="pb-3 text-left font-medium">Tổng giảm</th>
                                <th class="pb-3 text-left font-medium">Hạn dùng</th>
                                <th class="pb-3 text-left font-medium">Trạng thái</th>
                                <th class="pb-3 text-right font-medium">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="coupon in coupons.data" :key="coupon.id" class="hover:bg-muted/30 transition-colors">
                                <td class="py-3 pr-3">
                                    <div>
                                        <p class="font-mono font-semibold text-primary">{{ coupon.code }}</p>
                                        <p v-if="coupon.description" class="text-xs text-muted-foreground mt-0.5">{{ coupon.description }}</p>
                                    </div>
                                </td>
                                <td class="py-3 pr-3">
                                    <Badge :class="coupon.discount_type === 'percent' ? 'bg-violet-100 text-violet-800' : 'bg-blue-100 text-blue-800'">
                                        {{ coupon.discount_type === 'percent' ? 'Phần trăm' : 'Cố định' }}
                                    </Badge>
                                </td>
                                <td class="py-3 pr-3 font-semibold">
                                    {{ coupon.discount_type === 'percent' ? `${coupon.discount_value}%` : `${coupon.discount_value.toLocaleString('vi')}₫` }}
                                </td>
                                <td class="py-3 pr-3">
                                    <span>{{ coupon.uses_count }}<span v-if="coupon.max_uses" class="text-muted-foreground">/{{ coupon.max_uses }}</span></span>
                                </td>
                                <td class="py-3 pr-3 text-emerald-600 font-medium">{{ coupon.total_discount_given }}₫</td>
                                <td class="py-3 pr-3 text-xs text-muted-foreground">
                                    <span v-if="coupon.expires_at">{{ coupon.expires_at }}</span>
                                    <span v-else>Không giới hạn</span>
                                </td>
                                <td class="py-3 pr-3">
                                    <Badge :class="coupon.is_valid
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : coupon.status === 'inactive' ? 'bg-slate-100 text-slate-600' : 'bg-rose-100 text-rose-700'">
                                        {{ coupon.is_valid ? 'Hợp lệ' : coupon.status === 'inactive' ? 'Tắt' : 'Hết hạn' }}
                                    </Badge>
                                </td>
                                <td class="py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            class="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                            title="Chỉnh sửa"
                                            @click="openEditForm(coupon)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </button>
                                        <button
                                            class="rounded p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                            :title="coupon.status === 'active' ? 'Vô hiệu hoá' : 'Kích hoạt'"
                                            @click="toggleCoupon(coupon)"
                                        >
                                            <ToggleRight v-if="coupon.status === 'active'" class="size-3.5 text-emerald-500" />
                                            <ToggleLeft v-else class="size-3.5 text-slate-400" />
                                        </button>
                                        <button
                                            class="rounded p-1.5 text-muted-foreground hover:bg-rose-50 hover:text-rose-600 transition-colors"
                                            title="Xóa"
                                            @click="deleteCoupon(coupon)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="!coupons.data.length" class="py-12 text-center text-sm text-muted-foreground">
                        Không có coupon nào phù hợp.
                    </p>
                </div>

                <!-- Pagination -->
                <div v-if="coupons.last_page > 1" class="mt-4 flex flex-wrap justify-center gap-1">
                    <button
                        v-for="link in coupons.links"
                        :key="link.label"
                        :disabled="!link.url"
                        :class="[
                            'rounded px-3 py-1 text-xs transition',
                            link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                            !link.url ? 'cursor-not-allowed opacity-40' : '',
                        ]"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Create / Edit slide-over -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm" @click.self="closeForm">
            <div class="w-full max-w-md rounded-2xl bg-background shadow-2xl ring-1 ring-border">
                <div class="flex items-center justify-between border-b p-5">
                    <h2 class="text-lg font-semibold">
                        {{ editingCoupon ? `Chỉnh sửa — ${editingCoupon.code}` : 'Tạo mã coupon mới' }}
                    </h2>
                    <button class="rounded-lg p-1.5 hover:bg-muted transition-colors" @click="closeForm">
                        <X class="size-4" />
                    </button>
                </div>

                <div class="space-y-4 p-5">
                    <!-- Code -->
                    <div v-if="!editingCoupon" class="grid gap-1.5">
                        <Label for="code">Mã coupon <span class="text-rose-500">*</span></Label>
                        <Input id="code" v-model="formData.code" placeholder="VD: SUMMER25" class="uppercase" />
                        <p class="text-xs text-muted-foreground">Chỉ dùng chữ, số và dấu gạch ngang. Sẽ được đổi thành chữ hoa.</p>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-1.5">
                        <Label for="description">Mô tả (tùy chọn)</Label>
                        <Input id="description" v-model="formData.description" placeholder="VD: Giảm giá mùa hè 2025" />
                    </div>

                    <!-- Discount type + value -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label>Loại giảm giá</Label>
                            <Select v-model="formData.discount_type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percent">Phần trăm (%)</SelectItem>
                                    <SelectItem value="fixed">Số tiền cố định</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="discount_value">Giá trị ({{ discountLabel }})</Label>
                            <Input id="discount_value" v-model.number="formData.discount_value" type="number" min="0.01" :max="formData.discount_type === 'percent' ? 100 : undefined" step="1" />
                        </div>
                    </div>

                    <!-- Max uses -->
                    <div class="grid gap-1.5">
                        <Label for="max_uses">Giới hạn số lần dùng</Label>
                        <Input id="max_uses" v-model="formData.max_uses" type="number" min="1" placeholder="Để trống = không giới hạn" />
                    </div>

                    <!-- Date range -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="starts_at">Ngày bắt đầu</Label>
                            <Input id="starts_at" v-model="formData.starts_at" type="date" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="expires_at">Ngày hết hạn</Label>
                            <Input id="expires_at" v-model="formData.expires_at" type="date" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 border-t p-5">
                    <Button variant="outline" class="flex-1" @click="closeForm">Hủy</Button>
                    <Button class="flex-1" @click="submitForm">
                        <Check class="mr-2 size-4" />
                        {{ editingCoupon ? 'Cập nhật' : 'Tạo coupon' }}
                    </Button>
                </div>
            </div>
        </div>
    </Transition>
</template>
