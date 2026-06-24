<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    KeyRound, Plus, Search, Shield, ShieldCheck, ShieldOff, ShieldX,
    UserCheck, UserCog, UserX, Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogFooter,
    DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

// Flash notifications
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
toast.success(flash.success);
}

    if (flash?.error)   {
toast.error(flash.error);
}

    if (flash?.temp_password) {
        tempPassword.value = flash.temp_password;
        showTempPassword.value = true;
    }
}, { immediate: true, deep: true });

// Filters
const search     = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');
const statusFilter = ref(props.filters.status ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => {
 clearTimeout(timer); timer = setTimeout(applyFilter, 400); 
});

function applyFilter() {
    router.get('/super-admin/accounts', {
        search: search.value || undefined,
        role:   roleFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

// Temp password dialog
const tempPassword     = ref('');
const showTempPassword = ref(false);
const copied           = ref(false);
function copyPassword() {
    navigator.clipboard.writeText(tempPassword.value);
    copied.value = true;
    setTimeout(() => {
 copied.value = false; 
}, 2000);
}

// Reset password
const processingReset = ref<number | null>(null);
function resetPassword(account: { id: number; name: string }) {
    if (!confirm(`Reset mật khẩu cho "${account.name}"? Mật khẩu mới sẽ hiển thị ngay sau thao tác này.`)) {
return;
}

    processingReset.value = account.id;
    router.post(`/super-admin/accounts/${account.id}/reset-password`, {}, {
        onFinish: () => {
 processingReset.value = null; 
},
    });
}

// Disable 2FA
const processingDisable2FA = ref<number | null>(null);
function disable2FA(account: { id: number; name: string }) {
    if (!confirm(`Tắt xác thực 2FA cho "${account.name}"?`)) {
return;
}

    processingDisable2FA.value = account.id;
    router.post(`/super-admin/accounts/${account.id}/disable-2fa`, {}, {
        onFinish: () => {
 processingDisable2FA.value = null; 
},
    });
}

// Impersonate User
function impersonateUser(account: any) {
    if (!confirm(`Bạn có chắc chắn muốn đăng nhập sắm vai dưới danh nghĩa "${account.name}" không?`)) {
        return;
    }

    router.post(`/super-admin/impersonate/${account.id}`, {});
}

// Toggle status
const statusForm = useForm({ status: '' });
const showStatusDialog = ref(false);
const selectedAccount  = ref<{ id: number; name: string; status: string } | null>(null);

function openStatusDialog(account: any) {
    selectedAccount.value = account;
    statusForm.status = account.status === 'active' ? 'suspended' : 'active';
    showStatusDialog.value = true;
}
function submitStatus() {
    if (!selectedAccount.value) {
return;
}

    statusForm.patch(`/super-admin/accounts/${selectedAccount.value.id}/status`, {
        onSuccess: () => {
 showStatusDialog.value = false; 
},
    });
}

// Create admin account
const showCreateDialog = ref(false);
const createForm = useForm({ name: '', email: '', role: '' });
function submitCreate() {
    createForm.post('/super-admin/accounts', {
        onSuccess: () => { showCreateDialog.value = false; createForm.reset(); },
    });
}

// Update role
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

// Helpers
const roleLabel: Record<string, string> = {
    owner: 'Chủ sở hữu', manager: 'Quản lý', cashier: 'Thu ngân',
    kitchen: 'Bếp', inventory_staff: 'Kho', staff: 'Nhân viên',
    waiter: 'Nhân viên order',
    super_admin: 'Super Admin', system_admin: 'Quản trị hệ thống',
    billing_admin: 'Kế toán', support_specialist: 'Hỗ trợ KH',
};
const roleColor: Record<string, string> = {
    owner:           'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    manager:         'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
    cashier:         'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
    kitchen:         'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
    inventory_staff: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
    waiter:          'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300',
    super_admin:     'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    system_admin:    'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
    billing_admin:   'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
    support_specialist: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
};

const totalWith2FA   = computed(() => props.accounts.data.filter(a => a.has_2fa).length);
</script>

<template>
    <Head title="Quản lý tài khoản" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold">Quản lý tài khoản</h1>
                <p class="text-sm text-muted-foreground">
                    Tổng cộng {{ accounts.total ?? 0 }} tài khoản chủ doanh nghiệp
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Button v-if="adminSubRoles.length" @click="showCreateDialog = true" class="gap-1.5">
                    <Plus class="size-4" />
                    Tạo tài khoản Admin
                </Button>
                <a
                    href="/super-admin/audit-logs"
                    class="inline-flex items-center gap-2 rounded-md border px-4 py-2 text-sm font-medium hover:bg-muted transition-colors"
                >
                    <ShieldCheck class="size-4" />
                    Xem Audit Log
                </a>
            </div>
        </div>

        <!-- Stat mini cards -->
        <div class="grid grid-cols-3 gap-4">
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <UserCheck class="size-8 text-green-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Đang hoạt động</p>
                        <p class="text-2xl font-bold text-green-600">{{ accounts.total ?? 0 }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <ShieldCheck class="size-8 text-blue-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Đã bật 2FA (trang này)</p>
                        <p class="text-2xl font-bold text-blue-600">{{ totalWith2FA }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <Users class="size-8 text-purple-500" />
                    <div>
                        <p class="text-xs text-muted-foreground">Tổng trang này</p>
                        <p class="text-2xl font-bold">{{ accounts.data.length }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Bộ lọc -->
        <Card>
            <CardContent class="flex flex-wrap gap-3 p-4">
                <div class="relative min-w-64 flex-1">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Tìm tên, email..." class="pl-9" />
                </div>
                <select
                    v-model="roleFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    @change="applyFilter"
                >
                    <option value="">Tất cả vai trò</option>
                    <option value="owner">Chủ sở hữu</option>
                    <option value="manager">Quản lý</option>
                    <option value="cashier">Thu ngân</option>
                    <option value="kitchen">Bếp</option>
                    <option value="inventory_staff">Kho</option>
                    <option value="waiter">Nhân viên order</option>
                    <option value="system_admin">Quản trị hệ thống</option>
                    <option value="billing_admin">Kế toán</option>
                    <option value="support_specialist">Hỗ trợ KH</option>
                </select>
                <select
                    v-model="statusFilter"
                    class="h-9 rounded-md border bg-background px-3 text-sm"
                    @change="applyFilter"
                >
                    <option value="">Tất cả trạng thái</option>
                    <option value="active">Hoạt động</option>
                    <option value="suspended">Bị khoá</option>
                </select>
            </CardContent>
        </Card>

        <!-- Bảng tài khoản -->
        <Card>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="px-6 py-3 font-medium">Tài khoản</th>
                            <th class="px-4 py-3 font-medium">Nhà hàng</th>
                            <th class="px-4 py-3 font-medium">Vai trò</th>
                            <th class="px-4 py-3 font-medium">Bảo mật</th>
                            <th class="px-4 py-3 font-medium">Trạng thái</th>
                            <th class="px-4 py-3 font-medium">Đăng nhập cuối</th>
                            <th class="px-4 py-3 font-medium text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="a in accounts.data"
                            :key="a.id"
                            class="border-b last:border-0 hover:bg-muted/30 transition-colors"
                        >
                            <!-- Tài khoản -->
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <Avatar class="h-8 w-8 shrink-0">
                                        <AvatarFallback class="bg-primary/10 text-primary text-xs font-semibold">
                                            {{ getInitials(a.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <p class="font-medium">{{ a.name }}</p>
                                        <p class="text-xs text-muted-foreground">{{ a.email }}</p>
                                        <p v-if="a.phone" class="text-xs text-muted-foreground">{{ a.phone }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Nhà hàng -->
                            <td class="px-4 py-4 text-sm">{{ a.restaurant }}</td>

                            <!-- Vai trò -->
                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in a.roles"
                                        :key="role"
                                        :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', roleColor[role] ?? 'bg-gray-100 text-gray-700']"
                                    >
                                        {{ roleLabel[role] ?? role }}
                                    </span>
                                </div>
                            </td>

                            <!-- Bảo mật -->
                            <td class="px-4 py-4">
                                <div class="flex flex-col gap-1">
                                    <span
                                        :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium w-fit', a.has_2fa ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400']"
                                    >
                                        <ShieldCheck v-if="a.has_2fa" class="size-3" />
                                        <ShieldOff v-else class="size-3" />
                                        {{ a.has_2fa ? '2FA bật' : '2FA tắt' }}
                                    </span>
                                    <span
                                        :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs w-fit', a.email_verified ? 'text-green-600' : 'text-orange-500']"
                                    >
                                        {{ a.email_verified ? '✓ Email xác thực' : '⚠ Chưa xác thực' }}
                                    </span>
                                </div>
                            </td>

                            <!-- Trạng thái -->
                            <td class="px-4 py-4">
                                <span
                                    :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', a.status === 'active' ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300']"
                                >
                                    {{ a.status === 'active' ? 'Hoạt động' : 'Bị khoá' }}
                                </span>
                            </td>

                            <!-- Đăng nhập cuối -->
                            <td class="px-4 py-4 text-xs text-muted-foreground">
                                {{ a.last_login_at ?? 'Chưa đăng nhập' }}
                            </td>

                            <!-- Thao tác -->
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-1">
                                    <!-- Đổi role (chỉ hiện với admin sub-roles) -->
                                    <Button
                                        v-if="a.roles.some((r: string) => adminSubRoles.includes(r))"
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1.5 text-xs text-violet-600 hover:text-violet-700 dark:text-violet-400 font-semibold"
                                        title="Đổi vai trò"
                                        @click="openRoleDialog(a)"
                                    >
                                        <UserCog class="size-3.5" />
                                        Đổi role
                                    </Button>

                                    <!-- Sắm vai -->
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1.5 text-xs text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-semibold"
                                        title="Đăng nhập sắm vai"
                                        @click="impersonateUser(a)"
                                    >
                                        <UserCheck class="size-3.5" />
                                        Sắm vai
                                    </Button>

                                    <!-- Reset mật khẩu -->
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1.5 text-xs"
                                        :disabled="processingReset === a.id"
                                        title="Reset mật khẩu"
                                        @click="resetPassword(a)"
                                    >
                                        <KeyRound class="size-3.5" />
                                        Reset
                                    </Button>

                                    <!-- Tắt 2FA -->
                                    <Button
                                        v-if="a.has_2fa"
                                        variant="ghost"
                                        size="sm"
                                        class="gap-1.5 text-xs text-orange-600 hover:text-orange-700"
                                        :disabled="processingDisable2FA === a.id"
                                        title="Tắt 2FA"
                                        @click="disable2FA(a)"
                                    >
                                        <ShieldX class="size-3.5" />
                                        Tắt 2FA
                                    </Button>

                                    <!-- Khoá / Mở khoá -->
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        :class="['gap-1.5 text-xs', a.status === 'active' ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700']"
                                        @click="openStatusDialog(a)"
                                    >
                                        <UserX v-if="a.status === 'active'" class="size-3.5" />
                                        <UserCheck v-else class="size-3.5" />
                                        {{ a.status === 'active' ? 'Khoá' : 'Mở khoá' }}
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!accounts.data.length">
                            <td colspan="7" class="px-6 py-12 text-center text-muted-foreground">
                                Không tìm thấy tài khoản nào
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="accounts.last_page > 1" class="flex justify-center gap-1 border-t p-4">
                    <a
                        v-for="link in accounts.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="['px-3 py-1 rounded text-sm border', link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted', !link.url ? 'opacity-40 pointer-events-none' : '']"
                    />
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Dialog: Mật khẩu tạm thời -->
    <Dialog v-model:open="showTempPassword">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <KeyRound class="size-5 text-primary" />
                    Mật khẩu tạm thời
                </DialogTitle>
            </DialogHeader>
            <div class="space-y-3 py-2">
                <p class="text-sm text-muted-foreground">
                    Cung cấp mật khẩu này cho người dùng. Hệ thống không lưu mật khẩu dạng văn bản — đây là lần duy nhất bạn thấy nó.
                </p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 rounded bg-muted px-4 py-3 font-mono text-lg font-bold tracking-widest select-all">
                        {{ tempPassword }}
                    </code>
                </div>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="showTempPassword = false">Đóng</Button>
                <Button @click="copyPassword">
                    {{ copied ? '✓ Đã sao chép' : 'Sao chép' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Dialog: Tạo tài khoản Admin mới -->
    <Dialog v-model:open="showCreateDialog">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Plus class="size-5 text-primary" />
                    Tạo tài khoản Admin mới
                </DialogTitle>
            </DialogHeader>
            <form @submit.prevent="submitCreate" class="space-y-4 py-2">
                <div class="grid gap-1.5">
                    <Label for="create-name">Họ tên</Label>
                    <Input id="create-name" v-model="createForm.name" placeholder="Nguyễn Văn A" required />
                    <p v-if="createForm.errors.name" class="text-xs text-red-500">{{ createForm.errors.name }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label for="create-email">Email</Label>
                    <Input id="create-email" type="email" v-model="createForm.email" placeholder="admin@aventura.vn" required />
                    <p v-if="createForm.errors.email" class="text-xs text-red-500">{{ createForm.errors.email }}</p>
                </div>
                <div class="grid gap-1.5">
                    <Label>Vai trò</Label>
                    <select v-model="createForm.role" required class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                        <option value="" disabled>Chọn vai trò...</option>
                        <option v-for="r in adminSubRoles" :key="r" :value="r">{{ roleLabel[r] ?? r }}</option>
                    </select>
                    <p v-if="createForm.errors.role" class="text-xs text-red-500">{{ createForm.errors.role }}</p>
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
                    <UserCog class="size-5 text-violet-500" />
                    Đổi vai trò cho {{ roleTarget?.name }}
                </DialogTitle>
            </DialogHeader>
            <div class="py-3">
                <select v-model="roleForm.role" class="h-9 w-full rounded-md border bg-background px-3 text-sm">
                    <option v-for="r in adminSubRoles" :key="r" :value="r">{{ roleLabel[r] ?? r }}</option>
                </select>
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
            <p class="text-sm text-muted-foreground py-2">
                Bạn có chắc muốn
                <strong>{{ statusForm.status === 'suspended' ? 'khoá' : 'mở khoá' }}</strong>
                tài khoản <strong>{{ selectedAccount?.name }}</strong>?
                <span v-if="statusForm.status === 'suspended'" class="block mt-1 text-red-600">
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

