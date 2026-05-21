<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, Plus, Search, Filter, Eye, ShieldCheck, ShieldOff, Crown } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurants: {
        data: Array<{
            id: number; name: string; code: string; status: string;
            plan: string; plan_code: string; owner: string; owner_email: string;
            branches_count: number; employees_count: number; tables_count: number;
            created_at: string;
        }>;
        links: any[]; meta: any;
    };
    plans: Array<{ id: number; code: string; name: string }>;
    filters: { status?: string; plan?: string; search?: string };
}>();

const search   = ref(props.filters.search ?? '');
const status   = ref(props.filters.status ?? '');
const planFilter = ref(props.filters.plan ?? '');

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilter(), 400);
});

function applyFilter() {
    router.get('/super-admin/restaurants', {
        search: search.value || undefined,
        status: status.value || undefined,
        plan:   planFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

// Dialog tạo nhà hàng
const showCreate = ref(false);
const createForm = useForm({
    name: '', tax_code: '', phone: '', email: '', address: '',
    plan_id: '', owner_name: '', owner_email: '',
    timezone: 'Asia/Ho_Chi_Minh', currency: 'VND',
});
function submitCreate() {
    createForm.post('/super-admin/restaurants', {
        onSuccess: () => { showCreate.value = false; createForm.reset(); },
    });
}

// Dialog đổi trạng thái
const showStatus = ref(false);
const selectedRestaurant = ref<{ id: number; name: string; status: string } | null>(null);
const statusForm = useForm({ status: '', reason: '' });
function openStatus(r: any) {
    selectedRestaurant.value = r;
    statusForm.status = r.status;
    showStatus.value = true;
}
function submitStatus() {
    if (!selectedRestaurant.value) return;
    statusForm.patch(`/super-admin/restaurants/${selectedRestaurant.value.id}/status`, {
        onSuccess: () => { showStatus.value = false; },
    });
}

const statusColor: Record<string, string> = {
    active:    'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    suspended: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    expired:   'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
};
const statusLabel: Record<string, string> = {
    active: 'Hoạt động', suspended: 'Tạm ngưng', expired: 'Hết hạn',
};
</script>

<template>
    <Head title="Quản lý nhà hàng" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Quản lý nhà hàng</h1>
                <p class="text-sm text-muted-foreground">
                    Tổng cộng {{ restaurants.meta?.total ?? 0 }} nhà hàng
                </p>
            </div>
            <Button @click="showCreate = true" class="gap-2">
                <Plus class="size-4" /> Thêm nhà hàng
            </Button>
        </div>

        <!-- Bộ lọc -->
        <Card>
            <CardContent class="flex flex-wrap gap-3 p-4">
                <div class="relative flex-1 min-w-48">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Tìm tên, mã, mã thuế..." class="pl-9" />
                </div>
                <select
                    v-model="status"
                    @change="applyFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="active">Hoạt động</option>
                    <option value="suspended">Tạm ngưng</option>
                    <option value="expired">Hết hạn</option>
                </select>
                <select
                    v-model="planFilter"
                    @change="applyFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tất cả gói</option>
                    <option v-for="p in plans" :key="p.code" :value="p.code">{{ p.name }}</option>
                </select>
            </CardContent>
        </Card>

        <!-- Bảng danh sách -->
        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="px-6 py-3 font-medium">Nhà hàng</th>
                            <th class="px-4 py-3 font-medium">Gói</th>
                            <th class="px-4 py-3 font-medium">Tài nguyên</th>
                            <th class="px-4 py-3 font-medium">Trạng thái</th>
                            <th class="px-4 py-3 font-medium">Ngày tạo</th>
                            <th class="px-4 py-3 font-medium text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="r in restaurants.data"
                            :key="r.id"
                            class="border-b last:border-0 hover:bg-muted/30 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <p class="font-medium">{{ r.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ r.owner_email }}</p>
                                <p class="text-xs text-muted-foreground font-mono">{{ r.code }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <span :class="['flex items-center gap-1 font-medium text-xs', r.plan_code === 'PRO' ? 'text-purple-600' : 'text-muted-foreground']">
                                    <Crown v-if="r.plan_code === 'PRO'" class="size-3" />
                                    {{ r.plan }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-muted-foreground">
                                <span>{{ r.branches_count }} chi nhánh</span> ·
                                <span>{{ r.employees_count }} NV</span> ·
                                <span>{{ r.tables_count }} bàn</span>
                            </td>
                            <td class="px-4 py-4">
                                <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusColor[r.status]]">
                                    {{ statusLabel[r.status] ?? r.status }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-muted-foreground">{{ r.created_at }}</td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <Link :href="`/super-admin/restaurants/${r.id}`">
                                        <Button variant="ghost" size="icon-sm" title="Xem chi tiết">
                                            <Eye class="size-4" />
                                        </Button>
                                    </Link>
                                    <Button
                                        variant="ghost" size="icon-sm"
                                        :title="r.status === 'active' ? 'Tạm ngưng' : 'Kích hoạt'"
                                        @click="openStatus(r)"
                                    >
                                        <ShieldOff v-if="r.status === 'active'" class="size-4 text-yellow-600" />
                                        <ShieldCheck v-else class="size-4 text-green-600" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!restaurants.data.length">
                            <td colspan="6" class="px-6 py-12 text-center text-muted-foreground">
                                Không tìm thấy nhà hàng nào
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="restaurants.meta?.last_page > 1" class="flex justify-center gap-1 border-t p-4">
                    <Link
                        v-for="link in restaurants.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="['px-3 py-1 rounded text-sm border', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted', !link.url ? 'opacity-40 pointer-events-none' : '']"
                    />
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Dialog Tạo nhà hàng -->
    <Dialog v-model:open="showCreate">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>Thêm nhà hàng mới</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="grid gap-4 py-2">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Tên nhà hàng *</Label>
                        <Input v-model="createForm.name" placeholder="Nhà hàng ABC" required />
                        <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Mã số thuế</Label>
                        <Input v-model="createForm.tax_code" placeholder="0123456789" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Số điện thoại</Label>
                        <Input v-model="createForm.phone" placeholder="0901234567" />
                    </div>
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Email nhà hàng</Label>
                        <Input v-model="createForm.email" type="email" placeholder="contact@restaurant.com" />
                    </div>
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Địa chỉ</Label>
                        <Input v-model="createForm.address" placeholder="123 Đường ABC, Quận 1..." />
                    </div>
                    <div class="col-span-2 grid gap-1.5">
                        <Label>Gói dịch vụ *</Label>
                        <select v-model="createForm.plan_id" required class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                            <option value="">Chọn gói...</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <p v-if="createForm.errors.plan_id" class="text-xs text-destructive">{{ createForm.errors.plan_id }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Tên chủ sở hữu *</Label>
                        <Input v-model="createForm.owner_name" placeholder="Nguyễn Văn A" required />
                        <p v-if="createForm.errors.owner_name" class="text-xs text-destructive">{{ createForm.errors.owner_name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Email chủ sở hữu *</Label>
                        <Input v-model="createForm.owner_email" type="email" placeholder="owner@email.com" required />
                        <p v-if="createForm.errors.owner_email" class="text-xs text-destructive">{{ createForm.errors.owner_email }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showCreate = false">Hủy</Button>
                    <Button type="submit" :disabled="createForm.processing">
                        {{ createForm.processing ? 'Đang tạo...' : 'Tạo nhà hàng' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Dialog Đổi trạng thái -->
    <Dialog v-model:open="showStatus">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Đổi trạng thái: {{ selectedRestaurant?.name }}</DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitStatus" class="grid gap-4 py-2">
                <div class="grid gap-1.5">
                    <Label>Trạng thái mới</Label>
                    <select v-model="statusForm.status" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                        <option value="active">✅ Kích hoạt</option>
                        <option value="suspended">⏸ Tạm ngưng</option>
                        <option value="expired">❌ Hết hạn</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Lý do (tuỳ chọn)</Label>
                    <Input v-model="statusForm.reason" placeholder="Ghi chú lý do..." />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showStatus = false">Hủy</Button>
                    <Button type="submit" :disabled="statusForm.processing">Xác nhận</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
