<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    KeyRound, Plus, Search, ShieldCheck, ShieldOff, ShieldX,
    UserCheck, UserCog, UserX, Users, MoreHorizontal,
    Lightbulb, ShieldAlert, CheckCircle2, AlertTriangle, AlertCircle, Shield, CheckCircle,
    User, Mail, Check
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogFooter,
    DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuItem,
    DropdownMenuSeparator, DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
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
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
            toast.success('Đã tạo tài khoản Admin thành công!');
        },
        onError: () => {
            toast.error('Tạo tài khoản thất bại. Vui lòng kiểm tra lại thông tin!');
        }
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
        onSuccess: () => {
            showRoleDialog.value = false;
            toast.success('Đã cập nhật vai trò thành công!');
        },
        onError: () => {
            toast.error('Cập nhật vai trò thất bại. Vui lòng kiểm tra lại!');
        }
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

const totalVerifiedEmails = computed(() => props.accounts.data.filter(a => a.email_verified).length);
const totalAccountsOnPage = computed(() => props.accounts.data.length || 1);
const rate2FA = computed(() => Math.round((totalWith2FA.value / totalAccountsOnPage.value) * 100));
const rateVerified = computed(() => Math.round((totalVerifiedEmails.value / totalAccountsOnPage.value) * 100));

const securityAlerts = computed(() => {
    const alerts = [];
    const superAdminsWithout2FA = props.accounts.data.filter(a => !a.has_2fa && (a.roles.includes('super_admin') || a.roles.includes('system_admin')));
    if (superAdminsWithout2FA.length > 0) {
        alerts.push({
            type: 'critical',
            icon: ShieldAlert,
            title: 'Rủi ro bảo mật nghiêm trọng!',
            description: `Có ${superAdminsWithout2FA.length} quản trị viên cấp cao chưa bật 2FA. Cần yêu cầu bật ngay lập tức.`,
            color: 'text-rose-600 dark:text-rose-400',
            bg: 'bg-rose-100/50 dark:bg-rose-900/20',
            border: 'border-rose-200 dark:border-rose-800'
        });
    }
    
    if (rate2FA.value < 50) {
        alerts.push({
            type: 'warning',
            icon: AlertTriangle,
            title: 'Tỷ lệ 2FA quá thấp',
            description: `Chỉ ${rate2FA.value}% tài khoản trên trang này bật 2FA. Đề xuất gửi thông báo khuyến nghị.`,
            color: 'text-amber-600 dark:text-amber-400',
            bg: 'bg-amber-100/50 dark:bg-amber-900/20',
            border: 'border-amber-200 dark:border-amber-800'
        });
    }

    if (rateVerified.value < 100) {
        alerts.push({
            type: 'info',
            icon: Lightbulb,
            title: 'Khuyến nghị xác thực',
            description: `Còn ${totalAccountsOnPage.value - totalVerifiedEmails.value} tài khoản chưa xác thực email.`,
            color: 'text-sky-600 dark:text-sky-400',
            bg: 'bg-sky-100/50 dark:bg-sky-900/20',
            border: 'border-sky-200 dark:border-sky-800'
        });
    }

    if (alerts.length === 0) {
        alerts.push({
            type: 'success',
            icon: CheckCircle2,
            title: 'Hệ thống an toàn',
            description: 'Tất cả tài khoản trên trang này đã bật 2FA và xác thực email đầy đủ.',
            color: 'text-emerald-600 dark:text-emerald-400',
            bg: 'bg-emerald-100/50 dark:bg-emerald-900/20',
            border: 'border-emerald-200 dark:border-emerald-800'
        });
    }

    return alerts;
});

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
            <StatCard label="Đang hoạt động" :value="accounts.total ?? 0" :icon="UserCheck" color="emerald" class="transition-all duration-300 hover:scale-[1.02] hover:shadow-lg hover:shadow-emerald-500/10 group cursor-default" />
            <StatCard label="Đã bật 2FA (trang này)" :value="totalWith2FA" :icon="ShieldCheck" color="sky" class="transition-all duration-300 hover:scale-[1.02] hover:shadow-lg hover:shadow-sky-500/10 group cursor-default" />
            <StatCard label="Tổng trang này" :value="accounts.data.length" :icon="Users" color="violet" class="transition-all duration-300 hover:scale-[1.02] hover:shadow-lg hover:shadow-violet-500/10 group cursor-default" />
        </div>

        <!-- Insights Section -->
        <div class="grid gap-4 md:grid-cols-2">
            <!-- Thống kê Bảo mật -->
            <Card class="bg-background/40 backdrop-blur-sm border-border/60 hover:border-border transition-all">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base font-bold flex items-center gap-2">
                        <Shield class="size-5 text-primary" /> Thống kê bảo mật (trang này)
                    </CardTitle>
                    <CardDescription>Tiến độ phủ 2FA và xác thực email</CardDescription>
                </CardHeader>
                <CardContent class="space-y-5">
                    <!-- 2FA Progress -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium flex items-center gap-1.5">
                                <ShieldCheck class="size-4 text-emerald-500" /> Tỷ lệ bật 2FA
                            </span>
                            <span class="font-bold">{{ rate2FA }}%</span>
                        </div>
                        <div class="h-2 w-full bg-muted overflow-hidden rounded-full">
                            <div class="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 rounded-full transition-all duration-1000" :style="`width: ${rate2FA}%`"></div>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ totalWith2FA }} / {{ accounts.data.length }} tài khoản đã bảo vệ</p>
                    </div>

                    <!-- Email Verification Progress -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium flex items-center gap-1.5">
                                <CheckCircle class="size-4 text-sky-500" /> Đã xác thực Email
                            </span>
                            <span class="font-bold">{{ rateVerified }}%</span>
                        </div>
                        <div class="h-2 w-full bg-muted overflow-hidden rounded-full">
                            <div class="h-full bg-gradient-to-r from-sky-500 to-sky-400 rounded-full transition-all duration-1000" :style="`width: ${rateVerified}%`"></div>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ totalVerifiedEmails }} / {{ accounts.data.length }} tài khoản hợp lệ</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Gợi ý thông minh -->
            <Card class="bg-background/40 backdrop-blur-sm border-border/60 hover:border-border transition-all">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base font-bold flex items-center gap-2">
                        <Lightbulb class="size-5 text-amber-500" /> Khuyến nghị hệ thống
                    </CardTitle>
                    <CardDescription>Đánh giá bảo mật dựa trên dữ liệu hiện tại</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div 
                        v-for="(alert, idx) in securityAlerts" 
                        :key="idx"
                        :class="['flex items-start gap-3 p-3 rounded-lg border transition-all hover:scale-[1.01]', alert.bg, alert.border]"
                    >
                        <component :is="alert.icon" :class="['size-5 shrink-0 mt-0.5', alert.color]" />
                        <div class="space-y-1">
                            <h4 :class="['text-sm font-semibold', alert.color]">{{ alert.title }}</h4>
                            <p class="text-xs text-muted-foreground leading-relaxed">{{ alert.description }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <FilterBar>
            <div class="relative min-w-48 flex-1 group">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
                <Input v-model="search" placeholder="Tìm tên, email..." class="pl-9 bg-background/50 backdrop-blur-sm border-border/60 hover:border-border focus-visible:ring-primary/20 transition-all" />
            </div>
            <Select v-model="roleFilter" @update:model-value="applyFilter">
                <SelectTrigger class="w-[170px] bg-background/50 backdrop-blur-sm border-border/60 hover:border-border transition-all">
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
                <SelectTrigger class="w-[170px] bg-background/50 backdrop-blur-sm border-border/60 hover:border-border transition-all">
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
                <div class="flex items-center gap-3 group/account">
                    <div class="rounded-full bg-gradient-to-tr from-primary/20 to-primary/5 p-[2px] transition-all duration-300 group-hover/account:scale-105">
                        <Avatar class="size-8 shrink-0 border border-background">
                            <AvatarFallback class="bg-primary/10 text-xs font-semibold text-primary">
                                {{ getInitials(row.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </div>
                    <div>
                        <p class="font-medium group-hover/account:text-primary transition-colors">{{ row.name }}</p>
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
                        :class="['inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-[11px] font-semibold leading-none shadow-sm transition-colors hover:opacity-80', roleColor[role] ?? 'bg-slate-100/80 text-slate-700 border-slate-200/50 dark:bg-slate-800/40 dark:text-slate-300']"
                    >
                        {{ roleLabel[role] ?? role }}
                    </span>
                </div>
            </template>

            <template #cell-security="{ row }">
                <div class="space-y-1.5">
                    <div :class="['inline-flex items-center gap-1.5 rounded-md border px-2 py-1 text-[11px] font-semibold leading-none shadow-sm transition-colors hover:opacity-80', row.has_2fa ? 'bg-emerald-100/80 text-emerald-700 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-rose-100/80 text-rose-700 border-rose-200/50 dark:bg-rose-950/40 dark:text-rose-300']">
                        <ShieldCheck v-if="row.has_2fa" class="size-3" />
                        <ShieldOff v-else class="size-3" />
                        {{ row.has_2fa ? '2FA Bật' : '2FA Tắt' }}
                    </div>
                    <div :class="['inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-semibold leading-none shadow-sm transition-colors hover:opacity-80', row.email_verified ? 'bg-emerald-100/80 text-emerald-700 border-emerald-200/50 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100/80 text-amber-700 border-amber-200/50 dark:bg-amber-950/40 dark:text-amber-300']">
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
                <div class="flex justify-end">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" class="h-8 w-8 p-0 hover:bg-muted/60 data-[state=open]:bg-muted">
                                <span class="sr-only">Mở menu</span>
                                <MoreHorizontal class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48">
                            <DropdownMenuItem v-if="row.roles.some((r: string) => adminSubRoles.includes(r))" @click="openRoleDialog(row)" class="text-violet-600 focus:text-violet-700 focus:bg-violet-500/10 dark:text-violet-400 cursor-pointer">
                                <UserCog class="mr-2 h-4 w-4" /> Đổi vai trò
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="impersonateUser(row)" class="text-indigo-600 focus:text-indigo-700 focus:bg-indigo-500/10 dark:text-indigo-400 cursor-pointer">
                                <UserCheck class="mr-2 h-4 w-4" /> Sắm vai (đăng nhập hộ)
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="resetPassword(row)" :disabled="processingReset === row.id" class="cursor-pointer">
                                <KeyRound class="mr-2 h-4 w-4" /> Reset mật khẩu
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="row.has_2fa" @click="disable2FA(row)" :disabled="processingDisable2FA === row.id" class="text-orange-600 focus:text-orange-700 focus:bg-orange-500/10 cursor-pointer">
                                <ShieldX class="mr-2 h-4 w-4" /> Tắt 2FA
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem 
                                @click="openStatusDialog(row)" 
                                :class="['cursor-pointer font-medium', row.status === 'active' ? 'text-rose-600 focus:text-rose-700 focus:bg-rose-500/10' : 'text-emerald-600 focus:text-emerald-700 focus:bg-emerald-500/10']"
                            >
                                <UserX v-if="row.status === 'active'" class="mr-2 h-4 w-4" />
                                <UserCheck v-else class="mr-2 h-4 w-4" />
                                {{ row.status === 'active' ? 'Khoá tài khoản' : 'Mở khoá' }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
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
        <DialogContent class="max-w-md rounded-2xl border border-border/80 bg-background/95 backdrop-blur-md shadow-2xl p-0 overflow-hidden">
            <!-- Modal Header -->
            <DialogHeader class="p-6 border-b border-border/40 bg-muted/10">
                <DialogTitle class="text-base font-bold flex items-center gap-2">
                    <div class="size-8 bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 rounded-lg flex items-center justify-center shrink-0">
                        <Plus class="size-4.5" />
                    </div>
                    <span>Tạo tài khoản Admin mới</span>
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submitCreate" class="p-6 flex flex-col gap-4">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">Họ tên <span class="text-rose-500">*</span></Label>
                    <div class="relative flex items-center">
                        <div class="absolute left-3 text-muted-foreground pointer-events-none">
                            <User class="size-4 text-indigo-500" />
                        </div>
                        <Input v-model="createForm.name" placeholder="Nguyễn Văn A" class="pl-9.5 rounded-xl border-border focus-visible:ring-indigo-500/20 focus-visible:border-indigo-500" required />
                    </div>
                    <p v-if="createForm.errors.name" class="text-xs text-destructive font-semibold">{{ createForm.errors.name }}</p>
                </div>

                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">Email <span class="text-rose-500">*</span></Label>
                    <div class="relative flex items-center">
                        <div class="absolute left-3 text-muted-foreground pointer-events-none">
                            <Mail class="size-4 text-indigo-500" />
                        </div>
                        <Input type="email" v-model="createForm.email" placeholder="admin@aventura.vn" class="pl-9.5 rounded-xl border-border focus-visible:ring-indigo-500/20 focus-visible:border-indigo-500" required />
                    </div>
                    <p v-if="createForm.errors.email" class="text-xs text-destructive font-semibold">{{ createForm.errors.email }}</p>
                </div>

                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">Vai trò <span class="text-rose-500">*</span></Label>
                    <Select v-model="createForm.role">
                        <SelectTrigger class="rounded-xl border-border focus:ring-indigo-500/20 focus:border-indigo-500">
                            <SelectValue placeholder="Chọn vai trò..." />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl">
                            <SelectItem v-for="r in adminSubRoles" :key="r" :value="r" class="cursor-pointer">{{ roleLabel[r] ?? r }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="createForm.errors.role" class="text-xs text-destructive font-semibold">{{ createForm.errors.role }}</p>
                </div>

                <div class="flex gap-3 mt-4 pt-4 border-t border-border/40">
                    <Button variant="outline" type="button" class="flex-grow rounded-xl cursor-pointer" @click="showCreateDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="createForm.processing" class="flex-grow rounded-xl bg-gradient-to-r from-indigo-500 to-violet-500 hover:from-indigo-600 hover:to-violet-600 text-white font-bold transition-all shadow-md cursor-pointer">
                        <Check class="mr-1.5 size-4" />
                        {{ createForm.processing ? 'Đang tạo...' : 'Tạo tài khoản' }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Dialog: Đổi vai trò admin -->
    <Dialog v-model:open="showRoleDialog">
        <DialogContent class="max-w-md rounded-2xl border border-border/80 bg-background/95 backdrop-blur-md shadow-2xl p-0 overflow-hidden">
            <!-- Modal Header -->
            <DialogHeader class="p-6 border-b border-border/40 bg-muted/10">
                <DialogTitle class="text-base font-bold flex items-center gap-2">
                    <div class="size-8 bg-violet-500/10 text-violet-500 border border-violet-500/20 rounded-lg flex items-center justify-center shrink-0">
                        <UserCog class="size-4.5" />
                    </div>
                    <span>Đổi vai trò cho {{ roleTarget?.name }}</span>
                </DialogTitle>
            </DialogHeader>

            <div class="p-6 flex flex-col gap-4">
                <div class="grid gap-1.5">
                    <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1">Chọn vai trò quản trị mới</Label>
                    <Select v-model="roleForm.role">
                        <SelectTrigger class="rounded-xl border-border focus:ring-violet-500/20 focus:border-violet-500">
                            <SelectValue placeholder="Chọn vai trò..." />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl">
                            <SelectItem v-for="r in adminSubRoles" :key="r" :value="r" class="cursor-pointer">{{ roleLabel[r] ?? r }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="flex gap-3 mt-4 pt-4 border-t border-border/40">
                    <Button variant="outline" class="flex-grow rounded-xl cursor-pointer" @click="showRoleDialog = false">Hủy</Button>
                    <Button :disabled="roleForm.processing" class="flex-grow rounded-xl bg-gradient-to-r from-violet-500 to-purple-500 hover:from-violet-600 hover:to-purple-600 text-white font-bold transition-all shadow-md cursor-pointer" @click="submitRole">
                        <Check class="mr-1.5 size-4" />
                        {{ roleForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <!-- Dialog: Đổi trạng thái -->
    <Dialog v-model:open="showStatusDialog">
        <DialogContent class="max-w-md rounded-2xl border border-border/80 bg-background/95 backdrop-blur-md shadow-2xl p-0 overflow-hidden">
            <!-- Modal Header -->
            <DialogHeader class="p-6 border-b border-border/40 bg-muted/10">
                <DialogTitle class="text-base font-bold flex items-center gap-2">
                    <div class="size-8 rounded-lg flex items-center justify-center shrink-0 border"
                         :class="statusForm.status === 'suspended' ? 'bg-rose-500/10 text-rose-500 border-rose-500/20' : 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20'">
                        <component :is="statusForm.status === 'suspended' ? ShieldX : ShieldCheck" class="size-4.5" />
                    </div>
                    <span>{{ statusForm.status === 'suspended' ? 'Khoá tài khoản admin' : 'Mở khoá tài khoản admin' }}</span>
                </DialogTitle>
            </DialogHeader>

            <div class="p-6 flex flex-col gap-4">
                <p class="text-sm text-foreground/90 leading-relaxed">
                    Bạn có chắc muốn
                    <strong class="font-bold" :class="statusForm.status === 'suspended' ? 'text-rose-500' : 'text-emerald-500'">{{ statusForm.status === 'suspended' ? 'khoá' : 'mở khoá' }}</strong>
                    tài khoản của admin <strong class="font-bold text-foreground">{{ selectedAccount?.name }}</strong>?
                    <span v-if="statusForm.status === 'suspended'" class="mt-2 block text-xs bg-rose-500/5 text-rose-600 border border-rose-500/15 p-3 rounded-xl font-semibold leading-relaxed">
                        ⚠️ Cảnh báo: Người dùng này sẽ lập tức bị đăng xuất và không thể tiếp tục truy cập vào hệ thống quản trị SuperAdmin.
                    </span>
                    <span v-else class="mt-2 block text-xs bg-emerald-500/5 text-emerald-600 border border-emerald-500/15 p-3 rounded-xl font-semibold leading-relaxed">
                        Thông báo: Người dùng sẽ có thể đăng nhập lại và thực hiện các nhiệm vụ quản trị bình thường.
                    </span>
                </p>

                <div class="flex gap-3 mt-4 pt-4 border-t border-border/40">
                    <Button variant="outline" class="flex-grow rounded-xl cursor-pointer" @click="showStatusDialog = false">Hủy</Button>
                    <Button
                        class="flex-grow rounded-xl font-bold transition-all shadow-md cursor-pointer"
                        :class="statusForm.status === 'suspended' ? 'bg-rose-500 hover:bg-rose-600 text-white' : 'bg-emerald-500 hover:bg-emerald-600 text-white'"
                        :disabled="statusForm.processing"
                        @click="submitStatus"
                    >
                        {{ statusForm.processing ? 'Đang xử lý...' : 'Xác nhận' }}
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
