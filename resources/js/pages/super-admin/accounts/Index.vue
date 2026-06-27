<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    KeyRound, Plus, Search, ShieldCheck, ShieldOff, ShieldX,
    UserCheck, UserCog, UserX, Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogFooter,
    DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, FilterBar, DataTable, StatusBadge, Pagination } from '@/components/super-admin';
import type { Column } from '@/components/super-admin';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    accounts: {
        data: Array<{
            id: number; name: string; email: string; phone: string | null;
            status: string; roles: string[]; restaurant: string;
            restaurant_id: number | null; has_2fa: boolean;
            last_login_at: string | null; email_verified: boolean; created_at: string;
        }>;
        links: any[];
        total: number;
        last_page: number;
    };
    filters: { search?: string; role?: string; status?: string };
    adminSubRoles: string[];
}>();

const { getInitials } = useInitials();
const page = usePage();

watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
    if (flash?.temp_password) {
        tempPassword.value = flash.temp_password;
        showTempPassword.value = true;
    }
}, { immediate: true, deep: true });

const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');
const statusFilter = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilter, 400); });

function applyFilter() {
    router.get('/super-admin/accounts', {
        search: search.value || undefined,
        role: roleFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

const tempPassword = ref('');
const showTempPassword = ref(false);
const copied = ref(false);
function copyPassword() {
    navigator.clipboard.writeText(tempPassword.value);
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

const processingReset = ref<number | null>(null);
function resetPassword(account: { id: number; name: string }) {
    if (!confirm(`Reset mật khẩu cho "${account.name}"?`)) return;
    processingReset.value = account.id;
    router.post(`/super-admin/accounts/${account.id}/reset-password`, {}, {
        onFinish: () => { processingReset.value = null; },
    });
}

const processingDisable2FA = ref<number | null>(null);
function disable2FA(account: { id: number; name: string }) {
    if (!confirm(`Tắt xác thực 2FA cho "${account.name}"?`)) return;
    processingDisable2FA.value = account.id;
    router.post(`/super-admin/accounts/${account.id}/disable-2fa`, {}, {
        onFinish: () => { processingDisable2FA.value = null; },
    });
}

function impersonateUser(account: any) {
    if (!confirm(`Đăng nhập sắm vai dưới danh nghĩa "${account.name}"?`)) return;
    router.post(`/super-admin/impersonate/${account.id}`, {});
}

const statusForm = useForm({ status: '' });
const showStatusDialog = ref(false);
const selectedAccount = ref<{ id: number; name: string; status: string } | null>(null);
function openStatusDialog(account: any) {
    selectedAccount.value = account;
    statusForm.status = account.status === 'active' ? 'suspended' : 'active';
    showStatusDialog.value = true;
}
function submitStatus() {
    if (!selectedAccount.value) return;
    statusForm.patch(`/super-admin/accounts/${selectedAccount.value.id}/status`, {
        onSuccess: () => { showStatusDialog.value = false; },
    });
}

const showCreateDialog = ref(false);
const createForm = useForm({ name: '', email: '', role: '' });
function submitCreate() {
    createForm.post('/super-admin/accounts', {
        onSuccess: () => { showCreateDialog.value = false; createForm.reset(); },
    });
}

const showRoleDialog = ref(false);
const roleTarget = ref<{ id: number; name: string } | null>(null);
const roleForm = useForm({ role: '' });
function openRoleDialog(account: any) {
    roleTarget.value = account;
    const adminRole = (account.roles as string[]).find((r: string) => props.adminSubRoles.includes(r));
    roleForm.role = adminRole ?? '';
    showRoleDialog.value = true;
}
function submitRole() {
    if (!roleTarget.value) return;
    roleForm.patch(`/super-admin/accounts/${roleTarget.value.id}/role`, {
        onSuccess: () => { showRoleDialog.value = false; },
    });
}

const roleLabel: Record<string, string> = {
    owner: 'Chủ sở hữu', manager: 'Quản lý', cashier: 'Thu ngân',
    kitchen: 'Bếp', inventory_staff: 'Kho', staff: 'Nhân viên',
    waiter: 'Nhân viên order',
    super_admin: 'Super Admin', system_admin: 'Quản trị hệ thống',
    billing_admin: 'Kế toán', support_specialist: 'Hỗ trợ KH',
};
const roleColor: Record<string, string> = {
    owner: 'bg-purple-100/80 text-purple-800 border-purple-200/50 dark:bg-purple-950/40 dark:text-purple-300',
    manager: 'bg-blue-100/80 text-blue-800 border-blue-200/50 dark:bg-blue-950/40 dark:text-blue-300',
    cashier: 'bg-emerald-100/80 text-emerald-800 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300',
    kitchen: 'bg-orange-100/80 text-orange-800 border-orange-200/50 dark:bg-orange-950/40 dark:text-orange-300',
    inventory_staff: 'bg-amber-100/80 text-amber-800 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300',
    waiter: 'bg-teal-100/80 text-teal-800 border-teal-200/50 dark:bg-teal-950/40 dark:text-teal-300',
    super_admin: 'bg-rose-100/80 text-rose-800 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300',
    system_admin: 'bg-indigo-100/80 text-indigo-800 border-indigo-200/50 dark:bg-indigo-950/40 dark:text-indigo-300',
    billing_admin: 'bg-cyan-100/80 text-cyan-800 border-cyan-200/50 dark:bg-cyan-950/40 dark:text-cyan-300',
    support_specialist: 'bg-sky-100/80 text-sky-800 border-sky-200/50 dark:bg-sky-950/40 dark:text-sky-300',
};

const totalWith2FA = computed(() => props.accounts.data.filter(a => a.has_2fa).length);

const columns: Column[] = [
    { key: 'account', label: 'Tài khoản' },
    { key: 'restaurant', label: 'Nhà hàng' },
    { key: 'roles', label: 'Vai trò' },
    { key: 'security', label: 'Bảo mật' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'last_login_at', label: 'Đăng nhập cuối' },
    { key: 'actions', label: 'Thao tác', align: 'right' },
];
</script>

<template>
    <Head title="Quản lý tài khoản" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Quản lý tài khoản"
            :subtitle="`Tổng cộng ${accounts.total ?? 0} tài khoản chủ doanh nghiệp`"
            :icon="Users"
        >
            <template #actions>
                <Button v-if="adminSubRoles.length" @click="showCreateDialog = true" class="gap-1.5">
                    <Plus class="size-4" /> Tạo tài khoản Admin
                </Button>
                <a
                    href="/super-admin/audit-logs"
                    class="inline-flex items-center gap-2 rounded-lg border border-border/60 px-4 py-2 text-sm font-medium transition-colors hover:bg-muted"
                >
                    <ShieldCheck class="size-4" /> Xem Audit Log
                </a>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard label="Đang hoạt động" :value="accounts.total ?? 0" :icon="UserCheck" color="emerald" class="" />
            <StatCard label="Đã bật 2FA (trang này)" :value="totalWith2FA" :icon="ShieldCheck" color="sky" class="" />
            <StatCard label="Tổng trang này" :value="accounts.data.length" :icon="Users" color="violet" class="" />
        </div>

        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Tìm tên, email..." class="pl-9" />
            </div>
            <Select v-model="roleFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[170px]">
                    <SelectValue placeholder="Tất cả vai trò" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả vai trò</SelectItem>
                    <SelectItem value="owner">Chủ sở hữu</SelectItem>
                    <SelectItem value="manager">Quản lý</SelectItem>
                    <SelectItem value="cashier">Thu ngân</SelectItem>
                    <SelectItem value="kitchen">Bếp</SelectItem>
                    <SelectItem value="inventory_staff">Kho</SelectItem>
                    <SelectItem value="waiter">Nhân viên order</SelectItem>
                    <SelectItem value="system_admin">Quản trị hệ thống</SelectItem>
                    <SelectItem value="billing_admin">Kế toán</SelectItem>
                    <SelectItem value="support_specialist">Hỗ trợ KH</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="statusFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[170px]">
                    <SelectValue placeholder="Tất cả trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả trạng thái</SelectItem>
                    <SelectItem value="active">Hoạt động</SelectItem>
                    <SelectItem value="suspended">Bị khoá</SelectItem>
                </SelectContent>
            </Select>
        </FilterBar>

        <DataTable
            :columns="columns"
            :rows="accounts.data"
            :empty-icon="Users"
            empty-title="Không tìm thấy tài khoản nào"
            empty-description="Thử thay đổi bộ lọc hoặc tạo tài khoản mới"
            class=""
        >
            <template #cell-account="{ row }">
                <div class="flex items-center gap-3">
                    <Avatar class="size-8 shrink-0">
                        <AvatarFallback class="bg-primary/10 text-xs font-semibold text-primary">
                            {{ getInitials(row.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div>
                        <p class="font-medium">{{ row.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ row.email }}</p>
                        <p v-if="row.phone" class="text-xs text-muted-foreground">{{ row.phone }}</p>
                    </div>
                </div>
            </template>

            <template #cell-restaurant="{ row }">
                <span class="text-sm">{{ row.restaurant }}</span>
            </template>

            <template #cell-roles="{ row }">
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="role in row.roles"
                        :key="role"
                        :class="['inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[11px] font-semibold leading-none', roleColor[role] ?? 'bg-slate-100/80 text-slate-700 border-slate-200/50 dark:bg-slate-800/40 dark:text-slate-300']"
                    >
                        {{ roleLabel[role] ?? role }}
                    </span>
                </div>
            </template>

            <template #cell-security="{ row }">
                <div class="space-y-1.5">
                    <div :class="['inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-semibold leading-none', row.has_2fa ? 'bg-emerald-100/80 text-emerald-700 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-rose-100/80 text-rose-700 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300']">
                        <ShieldCheck v-if="row.has_2fa" class="size-3" />
                        <ShieldOff v-else class="size-3" />
                        {{ row.has_2fa ? '2FA Bật' : '2FA Tắt' }}
                    </div>
                    <div :class="['inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-semibold leading-none', row.email_verified ? 'bg-emerald-100/80 text-emerald-700 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100/80 text-amber-700 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300']">
                        {{ row.email_verified ? '✓ Đã xác thực' : '⚠ Chưa xác thực' }}
                    </div>
                </div>
            </template>

            <template #cell-status="{ row }">
                <StatusBadge :status="row.status">
                    {{ row.status === 'active' ? 'Hoạt động' : 'Bị khoá' }}
                </StatusBadge>
            </template>

            <template #cell-last_login_at="{ row }">
                <span class="text-xs text-muted-foreground">{{ row.last_login_at ?? 'Chưa đăng nhập' }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-end gap-0.5">
                    <Button
                        v-if="row.roles.some((r: string) => adminSubRoles.includes(r))"
                        variant="ghost" size="icon-sm"
                        title="Đổi vai trò"
                        class="text-violet-600 hover:text-violet-700 hover:bg-violet-500/10 dark:text-violet-400"
                        @click="openRoleDialog(row)"
                    >
                        <UserCog class="size-4" />
                    </Button>
                    <Button
                        variant="ghost" size="icon-sm"
                        title="Sắm vai (đăng nhập hộ)"
                        class="text-indigo-600 hover:text-indigo-700 hover:bg-indigo-500/10 dark:text-indigo-400"
                        @click="impersonateUser(row)"
                    >
                        <UserCheck class="size-4" />
                    </Button>
                    <Button
                        variant="ghost" size="icon-sm"
                        title="Reset mật khẩu"
                        :disabled="processingReset === row.id"
                        @click="resetPassword(row)"
                    >
                        <KeyRound class="size-4" />
                    </Button>
                    <Button
                        v-if="row.has_2fa"
                        variant="ghost" size="icon-sm"
                        title="Tắt 2FA"
                        class="text-orange-600 hover:text-orange-700 hover:bg-orange-500/10"
                        :disabled="processingDisable2FA === row.id"
                        @click="disable2FA(row)"
                    >
                        <ShieldX class="size-4" />
                    </Button>
                    <Button
                        variant="ghost" size="icon-sm"
                        :title="row.status === 'active' ? 'Khoá tài khoản' : 'Mở khoá'"
                        :class="row.status === 'active' ? 'text-rose-600 hover:text-rose-700 hover:bg-rose-500/10' : 'text-emerald-600 hover:text-emerald-700 hover:bg-emerald-500/10'"
                        @click="openStatusDialog(row)"
                    >
                        <UserX v-if="row.status === 'active'" class="size-4" />
                        <UserCheck v-else class="size-4" />
                    </Button>
                </div>
            </template>

            <template #pagination>
                <Pagination v-if="accounts.last_page > 1" :links="accounts.links" />
            </template>
        </DataTable>
    </div>

    <!-- Dialog: Mật khẩu tạm thời -->
    <Dialog v-model:open="showTempPassword">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <KeyRound class="size-5 text-primary" /> Mật khẩu tạm thời
                </DialogTitle>
            </DialogHeader>
            <div class="space-y-3 py-2">
                <p class="text-sm text-muted-foreground">
                    Cung cấp mật khẩu này cho người dùng. Đây là lần duy nhất bạn thấy nó.
                </p>
                <code class="block select-all rounded bg-muted px-4 py-3 font-mono text-lg font-bold tracking-widest">
                    {{ tempPassword }}
                </code>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="showTempPassword = false">Đóng</Button>
                <Button @click="copyPassword">{{ copied ? '✓ Đã sao chép' : 'Sao chép' }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Dialog: Tạo tài khoản Admin -->
    <Dialog v-model:open="showCreateDialog">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="size-5 text-primary" /> Tạo tài khoản Admin mới
                </DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                <div class="grid gap-1.5">
                    <Label>Họ tên</Label>
                    <Input v-model="createForm.name" placeholder="Nguyễn Văn A" required />
                    <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label>Email</Label>
                    <Input type="email" v-model="createForm.email" placeholder="admin@aventura.vn" required />
                    <p v-if="createForm.errors.email" class="text-xs text-destructive">{{ createForm.errors.email }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label>Vai trò</Label>
                    <Select v-model="createForm.role">
                        <SelectTrigger>
                            <SelectValue placeholder="Chọn vai trò..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="r in adminSubRoles" :key="r" :value="r">{{ roleLabel[r] ?? r }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="createForm.errors.role" class="text-xs text-destructive">{{ createForm.errors.role }}</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" type="button" @click="showCreateDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="createForm.processing">
                        {{ createForm.processing ? 'Đang tạo...' : 'Tạo tài khoản' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Dialog: Đổi vai trò admin -->
    <Dialog v-model:open="showRoleDialog">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <UserCog class="size-5 text-violet-500" /> Đổi vai trò cho {{ roleTarget?.name }}
                </DialogTitle>
            </DialogHeader>
            <div class="py-3">
                <Select v-model="roleForm.role">
                    <SelectTrigger>
                        <SelectValue placeholder="Chọn vai trò..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="r in adminSubRoles" :key="r" :value="r">{{ roleLabel[r] ?? r }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showRoleDialog = false">Hủy</Button>
                <Button :disabled="roleForm.processing" @click="submitRole">
                    {{ roleForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Dialog: Đổi trạng thái -->
    <Dialog v-model:open="showStatusDialog">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>
                    {{ statusForm.status === 'suspended' ? 'Khoá tài khoản' : 'Mở khoá tài khoản' }}
                </DialogTitle>
            </DialogHeader>
            <p class="py-2 text-sm text-muted-foreground">
                Bạn có chắc muốn
                <strong>{{ statusForm.status === 'suspended' ? 'khoá' : 'mở khoá' }}</strong>
                tài khoản <strong>{{ selectedAccount?.name }}</strong>?
                <span v-if="statusForm.status === 'suspended'" class="mt-1 block text-rose-600">
                    Người dùng sẽ không thể đăng nhập vào hệ thống.
                </span>
            </p>
            <DialogFooter>
                <Button variant="outline" @click="showStatusDialog = false">Hủy</Button>
                <Button
                    :variant="statusForm.status === 'suspended' ? 'destructive' : 'default'"
                    :disabled="statusForm.processing"
                    @click="submitStatus"
                >
                    {{ statusForm.processing ? 'Đang xử lý...' : 'Xác nhận' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
