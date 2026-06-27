<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, Search, Eye, ShieldCheck, ShieldOff, Crown, UserCheck, Building2, CheckCircle, CreditCard, Ban } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, FilterBar, DataTable, StatusBadge, Pagination, ProgressBar } from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
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

function impersonateUser(ownerId: number | undefined) {
    if (!ownerId) {
        alert('Không tìm thấy tài khoản chủ sở hữu để sắm vai.');
        return;
    }
    if (confirm('Bạn có chắc chắn muốn đăng nhập sắm vai dưới quyền của tài khoản chủ sở hữu này không?')) {
        router.post(`/super-admin/impersonate/${ownerId}`);
    }
}

function formatQuota(used: number, limit: number | null) {
    return limit === null ? `${used}/∞` : `${used}/${limit}`;
}

function quotaPercent(used: number, limit: number | null) {
    if (limit === null || limit === 0) return 0;
    return Math.round((used / limit) * 100);
}

const columns: Column[] = [
    { key: 'name', label: 'Nhà hàng' },
    { key: 'plan', label: 'Gói' },
    { key: 'quota', label: 'Tài nguyên' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'created_at', label: 'Ngày tạo' },
    { key: 'actions', label: 'Thao tác', align: 'right' },
];

const statusLabel: Record<string, string> = {
    active: 'Hoạt động', suspended: 'Tạm ngưng', expired: 'Hết hạn',
};

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
</script>

<template>
    <Head title="Quản lý nhà hàng" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Quản lý nhà hàng"
            :subtitle="`Tổng cộng ${restaurants.total ?? 0} nhà hàng`"
            :icon="Building2"
        >
            <template #actions>
                <Button @click="showCreate = true" class="gap-2">
                    <Plus class="size-4" /> Thêm nhà hàng
                </Button>
            </template>
        </PageHeader>

        <!-- Thống kê tổng quan -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard
                label="Tổng nhà hàng"
                :value="stats?.total ?? 0"
                :icon="Building2"
                color="blue"
                class=""
            />
            <StatCard
                label="Đang hoạt động"
                :value="stats?.active ?? 0"
                :icon="CheckCircle"
                color="emerald"
                class=""
            />
            <StatCard
                label="Gói trả phí"
                :value="stats?.paid ?? 0"
                :icon="CreditCard"
                color="purple"
                class=""
            />
            <StatCard
                label="Tạm ngưng / Khóa"
                :value="stats?.suspended ?? 0"
                :icon="Ban"
                color="amber"
                class=""
            />
            <StatCard
                label="Gắn cờ (Hậu mãi)"
                :value="stats?.flagged ?? 0"
                :icon="Ban"
                color="rose"
                clickable
                class=""
                @click="() => { flaggedFilter = flaggedFilter === '1' ? '' : '1'; applyFilter(); }"
            />
        </div>

        <!-- Bộ lọc -->
        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Tìm tên, mã, mã thuế..." class="pl-9" />
            </div>
            <Select v-model="status" @update:model-value="applyFilter">
                <SelectTrigger class="w-[170px]">
                    <SelectValue placeholder="Tất cả trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả trạng thái</SelectItem>
                    <SelectItem value="active">Hoạt động</SelectItem>
                    <SelectItem value="suspended">Tạm ngưng</SelectItem>
                    <SelectItem value="expired">Hết hạn</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="planFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Tất cả gói" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả gói</SelectItem>
                    <SelectItem v-for="p in plans" :key="p.code" :value="p.code">{{ p.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="flaggedFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[210px]">
                    <SelectValue placeholder="Tất cả hoạt động" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả hoạt động</SelectItem>
                    <SelectItem value="1">🚩 Chỉ bị gắn cờ</SelectItem>
                </SelectContent>
            </Select>
        </FilterBar>

        <!-- Bảng danh sách -->
        <DataTable
            :columns="columns"
            :rows="restaurants.data"
            :empty-icon="Building2"
            empty-title="Không tìm thấy nhà hàng nào"
            empty-description="Thử thay đổi bộ lọc hoặc thêm nhà hàng mới"
            class=""
        >
            <template #cell-name="{ row }">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <p class="font-medium">{{ row.name }}</p>
                    <span
                        v-if="row.is_inactive_flagged"
                        class="inline-flex items-center gap-1 rounded-md bg-rose-500/10 px-1.5 py-0.5 text-[9px] font-bold text-rose-700 dark:text-rose-300 border border-rose-500/20"
                    >
                        🚩 Cần hậu mãi
                    </span>
                </div>
                <p class="text-xs text-muted-foreground">{{ row.owner_email }}</p>
                <p class="font-mono text-xs text-muted-foreground">{{ row.code }}</p>
                <p class="mt-0.5 text-[10px] text-muted-foreground/70">Hoạt động cuối: {{ row.last_active_at }}</p>
            </template>

            <template #cell-plan="{ row }">
                <span :class="['flex items-center gap-1 text-xs font-medium', row.plan_code === 'PRO' ? 'text-purple-600 dark:text-purple-400' : 'text-muted-foreground']">
                    <Crown v-if="row.plan_code === 'PRO'" class="size-3" />
                    {{ row.plan }}
                </span>
            </template>

            <template #cell-quota="{ row }">
                <div class="space-y-1.5 min-w-[140px]">
                    <div class="flex items-center gap-2">
                        <span class="w-7 text-[10px] font-mono text-muted-foreground">CN</span>
                        <ProgressBar
                            :value="row.branches_count"
                            :max="row.max_branches ?? 999"
                            class="flex-1"
                        />
                        <span class="text-[10px] font-mono text-muted-foreground tabular-nums">{{ formatQuota(row.branches_count, row.max_branches) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-7 text-[10px] font-mono text-muted-foreground">NV</span>
                        <ProgressBar
                            :value="row.employees_count"
                            :max="row.max_users ?? 999"
                            class="flex-1"
                        />
                        <span class="text-[10px] font-mono text-muted-foreground tabular-nums">{{ formatQuota(row.employees_count, row.max_users) }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-7 text-[10px] font-mono text-muted-foreground">Bàn</span>
                        <ProgressBar
                            :value="row.tables_count"
                            :max="row.max_tables ?? 999"
                            class="flex-1"
                        />
                        <span class="text-[10px] font-mono text-muted-foreground tabular-nums">{{ formatQuota(row.tables_count, row.max_tables) }}</span>
                    </div>
                </div>
            </template>

            <template #cell-status="{ row }">
                <StatusBadge :status="row.status">
                    {{ statusLabel[row.status] ?? row.status }}
                </StatusBadge>
            </template>

            <template #cell-created_at="{ row }">
                <span class="text-muted-foreground">{{ row.created_at }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-1">
                    <Link :href="`/super-admin/restaurants/${row.id}`">
                        <Button variant="ghost" size="icon-sm" title="Xem chi tiết">
                            <Eye class="size-4" />
                        </Button>
                    </Link>
                    <Button
                        v-if="row.owner_id"
                        variant="ghost" size="icon-sm"
                        title="Sắm vai (Đăng nhập hộ)"
                        class="text-blue-600 hover:bg-blue-50 hover:text-blue-700 dark:text-blue-400 dark:hover:bg-blue-950/20"
                        @click="impersonateUser(row.owner_id)"
                    >
                        <UserCheck class="size-4" />
                    </Button>
                    <Button
                        variant="ghost" size="icon-sm"
                        :title="row.status === 'active' ? 'Tạm ngưng' : 'Kích hoạt'"
                        @click="openStatus(row)"
                    >
                        <ShieldOff v-if="row.status === 'active'" class="size-4 text-amber-600" />
                        <ShieldCheck v-else class="size-4 text-emerald-600" />
                    </Button>
                </div>
            </template>

            <template #pagination>
                <Pagination v-if="restaurants.last_page > 1" :links="restaurants.links" />
            </template>
        </DataTable>
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
                        <Select v-model="createForm.plan_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Chọn gói..." />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in plans" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                            </SelectContent>
                        </Select>
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
                    <Select v-model="statusForm.status">
                        <SelectTrigger>
                            <SelectValue placeholder="Chọn trạng thái..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">✅ Kích hoạt</SelectItem>
                            <SelectItem value="suspended">⏸ Tạm ngưng</SelectItem>
                            <SelectItem value="expired">❌ Hết hạn</SelectItem>
                        </SelectContent>
                    </Select>
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
