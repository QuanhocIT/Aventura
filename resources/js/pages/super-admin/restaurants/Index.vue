<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, Eye, ShieldCheck, ShieldOff, Crown, UserCheck, Building2, CheckCircle, CreditCard, Ban } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurants: {
        data: Array<{
            id: number; name: string; code: string; status: string;
            plan: string; plan_code: string; owner: string; owner_email: string;
            owner_id?: number;
            branches_count: number; employees_count: number; tables_count: number;
            max_branches: number | null; max_tables: number | null; max_users: number | null;
            created_at: string;
            is_inactive_flagged?: boolean;
            last_active_at?: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    plans: Array<{ id: number; code: string; name: string }>;
    filters: { status?: string; plan?: string; search?: string; flagged?: string };
    stats: { total: number; active: number; paid: number; suspended: number; flagged?: number };
}>();

const search   = ref(props.filters.search ?? '');
const status   = ref(props.filters.status ?? '');
const planFilter = ref(props.filters.plan ?? '');
const flaggedFilter = ref(props.filters.flagged ?? '');

let searchTimer: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => applyFilter(), 400);
});

function applyFilter() {
    router.get('/super-admin/restaurants', {
        search: search.value || undefined,
        status: status.value || undefined,
        plan:   planFilter.value || undefined,
        flagged: flaggedFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

// Sắm vai (Đăng nhập hộ)
function impersonateUser(ownerId: number | undefined) {
    if (!ownerId) {
        alert('Không tìm thấy tài khoản chủ sở hữu để sắm vai.');

        return;
    }

    if (confirm('Bạn có chắc chắn muốn đăng nhập sắm vai dưới quyền của tài khoản chủ sở hữu này không?')) {
        router.post(`/super-admin/impersonate/${ownerId}`);
    }
}

// Định dạng & tô màu giới hạn tài nguyên
function getQuotaColor(used: number, limit: number | null) {
    if (limit === null) {
return 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 border-emerald-100 dark:border-emerald-900/30';
}

    const percentage = (used / limit) * 100;

    if (percentage >= 100) {
return 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 border-rose-100 dark:border-rose-900/30 font-semibold';
}

    if (percentage >= 80) {
return 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/20 border-amber-100 dark:border-amber-900/30';
}

    return 'text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/20 border-slate-100 dark:border-slate-800';
}

function formatQuota(used: number, limit: number | null) {
    if (limit === null) {
return `${used}/∞`;
}

    return `${used}/${limit}`;
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
        onSuccess: () => {
 showCreate.value = false; createForm.reset(); 
},
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
    if (!selectedRestaurant.value) {
 return;
}

    statusForm.patch(`/super-admin/restaurants/${selectedRestaurant.value.id}/status`, {
        onSuccess: () => {
 showStatus.value = false; 
},
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
                    Tổng cộng {{ restaurants.total ?? 0 }} nhà hàng
                </p>
            </div>
            <Button @click="showCreate = true" class="gap-2">
                <Plus class="size-4" /> Thêm nhà hàng
            </Button>
        </div>

        <!-- Thống kê tổng quan -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card class="overflow-hidden border-border bg-card shadow-sm">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-lg bg-blue-50 p-2.5 text-blue-600 dark:bg-blue-950/30 dark:text-blue-400">
                        <Building2 class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Tổng nhà hàng</span>
                        <span class="text-xl font-bold tracking-tight">{{ stats?.total ?? 0 }}</span>
                    </div>
                </CardContent>
            </Card>
            <Card class="overflow-hidden border-border bg-card shadow-sm">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-lg bg-emerald-50 p-2.5 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400">
                        <CheckCircle class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Đang hoạt động</span>
                        <span class="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">{{ stats?.active ?? 0 }}</span>
                    </div>
                </CardContent>
            </Card>
            <Card class="overflow-hidden border-border bg-card shadow-sm">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-lg bg-purple-50 p-2.5 text-purple-600 dark:bg-purple-950/30 dark:text-purple-400">
                        <CreditCard class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Gói trả phí</span>
                        <span class="text-xl font-bold tracking-tight text-purple-600 dark:text-purple-400">{{ stats?.paid ?? 0 }}</span>
                    </div>
                </CardContent>
            </Card>
            <Card class="overflow-hidden border-border bg-card shadow-sm">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-lg bg-amber-50 p-2.5 text-amber-600 dark:bg-amber-950/30 dark:text-amber-400">
                        <Ban class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Tạm ngưng / Khóa</span>
                        <span class="text-xl font-bold tracking-tight text-amber-600 dark:text-amber-400">{{ stats?.suspended ?? 0 }}</span>
                    </div>
                </CardContent>
            </Card>
            <Card class="overflow-hidden border-border bg-card shadow-sm cursor-pointer hover:bg-muted/40 transition-colors" @click="() => { flaggedFilter = flaggedFilter === '1' ? '' : '1'; applyFilter(); }">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-lg bg-rose-50 p-2.5 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400">
                        <Ban class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Gắn cờ (Hậu mãi)</span>
                        <span class="text-xl font-bold tracking-tight text-rose-600 dark:text-rose-400 font-mono">{{ stats?.flagged ?? 0 }}</span>
                    </div>
                </CardContent>
            </Card>
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
                <select
                    v-model="flaggedFilter"
                    @change="applyFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">Tất cả hoạt động</option>
                    <option value="1">🚩 Chỉ bị gắn cờ (Cần hậu mãi)</option>
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
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="font-medium">{{ r.name }}</p>
                                    <span v-if="r.is_inactive_flagged" class="inline-flex items-center gap-1 rounded bg-rose-100 px-1.5 py-0.5 text-[9px] font-bold text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
                                        🚩 Cần hậu mãi
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground">{{ r.owner_email }}</p>
                                <p class="text-xs text-muted-foreground font-mono">{{ r.code }}</p>
                                <p class="text-[10px] text-slate-500 mt-0.5">Hoạt động cuối: {{ r.last_active_at }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <span :class="['flex items-center gap-1 font-medium text-xs', r.plan_code === 'PRO' ? 'text-purple-600' : 'text-muted-foreground']">
                                    <Crown v-if="r.plan_code === 'PRO'" class="size-3" />
                                    {{ r.plan }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span :class="['inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-mono border', getQuotaColor(r.branches_count, r.max_branches)]" title="Chi nhánh">
                                        CN: {{ formatQuota(r.branches_count, r.max_branches) }}
                                    </span>
                                    <span :class="['inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-mono border', getQuotaColor(r.employees_count, r.max_users)]" title="Nhân viên">
                                        NV: {{ formatQuota(r.employees_count, r.max_users) }}
                                    </span>
                                    <span :class="['inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-mono border', getQuotaColor(r.tables_count, r.max_tables)]" title="Bàn ăn">
                                        Bàn: {{ formatQuota(r.tables_count, r.max_tables) }}
                                    </span>
                                </div>
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
                                        v-if="r.owner_id"
                                        variant="ghost" size="icon-sm"
                                        title="Sắm vai (Đăng nhập hộ)"
                                        class="text-blue-600 dark:text-blue-400 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-950/20"
                                        @click="impersonateUser(r.owner_id)"
                                    >
                                        <UserCheck class="size-4" />
                                    </Button>
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
                <div v-if="restaurants.last_page > 1" class="flex justify-center gap-1 border-t p-4">
                    <Link
                        v-for="link in restaurants.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        :class="['px-3 py-1 rounded text-sm border', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted', !link.url ? 'opacity-40 pointer-events-none' : '']"
                    >
                        <span v-html="link.label" />
                    </Link>
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


