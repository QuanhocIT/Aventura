<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, RefreshCw, ShieldAlert } from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Issue = {
    id: string;
    type: string;
    severity: 'critical' | 'warning';
    title: string;
    message: string;
    tenant_id: number;
    tenant_name?: string | null;
    branch_id?: number | null;
    branch_name?: string | null;
    user_id?: number | null;
    user_name?: string | null;
    user_email?: string | null;
};

type Tenant = {
    id: number;
    name: string;
    code: string;
    status: string;
    lifecycle_status: string;
    plan: string;
    plan_code: string;
    owner?: string | null;
    branches_count: number;
    features: Record<string, boolean>;
    feature_overrides: Record<string, boolean>;
};

type Branch = {
    id: number;
    tenant_id: number;
    name: string;
    status: string;
};

type Candidate = {
    id: number;
    tenant_id: number;
    name: string;
    email: string;
    branch_id?: number | null;
    roles?: string[];
};

const props = defineProps<{
    summary: {
        tenants: number;
        branches: number;
        issues: number;
        critical: number;
        by_type: Record<string, number>;
    };
    issues: Issue[];
    tenants: Tenant[];
    branches: Branch[];
    manager_candidates: Candidate[];
    owner_candidates: Candidate[];
    feature_flags: Array<{ key: string; label: string }>;
    lifecycle_statuses: string[];
}>();

const managerSelection = reactive<Record<string, string>>({});
const ownerSelection = reactive<Record<string, string>>({});
const branchSelection = reactive<Record<string, string>>({});
const busy = ref<string | null>(null);

const activeBranchesFor = (tenantId: number) =>
    props.branches.filter((branch) => branch.tenant_id === tenantId && branch.status === 'active');

const managersFor = (tenantId: number) =>
    props.manager_candidates.filter((candidate) => candidate.tenant_id === tenantId);

const ownersFor = (tenantId: number) =>
    props.owner_candidates.filter((candidate) => candidate.tenant_id === tenantId);

const lifecycleLabel = (status: string) =>
    ({
        active: 'Active',
        suspended: 'Suspended',
        archived: 'Archived',
        sandbox: 'Sandbox',
    })[status] ?? status;

const issueLabel: Record<string, string> = {
    manager_without_branch: 'Manager chưa có branch',
    branch_without_manager: 'Branch chưa có manager',
    tenant_without_owner: 'Tenant chưa có owner',
    wrong_assignment: 'Gán sai tenant/branch',
    inactive_branch_active_user: 'Branch inactive còn user active',
};

function submit(payload: Record<string, unknown>, key: string, message: string) {
    busy.value = key;
    router.post('/super-admin/tenant-health/repair', payload, {
        preserveScroll: true,
        onSuccess: () => toast.success(message),
        onError: (errors: Record<string, string>) => toast.error((Object.values(errors)[0] as string | undefined) ?? 'Không thể thực hiện thao tác.'),
        onFinish: () => { busy.value = null; },
    });
}

function repairManager(issue: Issue) {
    const managerId = managerSelection[issue.id];
    const branchId = issue.branch_id ?? branchSelection[issue.id];
    if (!managerId || !branchId) {
        toast.error('Hãy chọn manager và branch.');
        return;
    }
    submit({ action: 'assign_manager', tenant_id: issue.tenant_id, branch_id: Number(branchId), user_id: Number(managerId) }, issue.id, 'Đã gán manager.');
}

function repairOwner(issue: Issue) {
    const userId = ownerSelection[issue.id];
    if (!userId) {
        toast.error('Hãy chọn owner.');
        return;
    }
    submit({ action: 'assign_owner', tenant_id: issue.tenant_id, user_id: Number(userId) }, issue.id, 'Đã gán owner.');
}

function repairBranch(issue: Issue) {
    const branchId = branchSelection[issue.id];
    if (!branchId || !issue.user_id) {
        toast.error('Hãy chọn branch hợp lệ.');
        return;
    }
    submit({ action: 'reassign_branch', tenant_id: issue.tenant_id, branch_id: Number(branchId), user_id: issue.user_id }, issue.id, 'Đã đồng bộ branch assignment.');
}

function clearBranch(issue: Issue) {
    if (!issue.user_id) return;
    submit({ action: 'clear_branch', user_id: issue.user_id }, `${issue.id}:clear`, 'Đã xóa branch assignment lỗi.');
}

function updateLifecycle(tenant: Tenant, lifecycleStatus: string) {
    submit({ action: 'set_lifecycle', tenant_id: tenant.id, lifecycle_status: lifecycleStatus }, `lifecycle:${tenant.id}`, 'Đã cập nhật lifecycle tenant.');
}

function updateFeature(tenant: Tenant, feature: string, enabled: boolean, mode = 'override') {
    submit({ action: 'set_feature_flag', tenant_id: tenant.id, feature, enabled, mode }, `feature:${tenant.id}:${feature}`, 'Đã cập nhật feature flag.');
}
</script>

<template>
    <Head title="Tenant/Branch Health" />

    <div class="space-y-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">System administration</p>
                <h1 class="text-2xl font-semibold tracking-tight">Tenant/Branch Health</h1>
                <p class="mt-1 text-sm text-muted-foreground">Phát hiện và sửa các lỗi owner, manager, branch và feature flag.</p>
            </div>
            <button class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm hover:bg-muted" @click="router.reload()">
                <RefreshCw class="size-4" /> Làm mới
            </button>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Tenants</p><p class="mt-1 text-2xl font-semibold">{{ summary.tenants }}</p></div>
            <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Branches</p><p class="mt-1 text-2xl font-semibold">{{ summary.branches }}</p></div>
            <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Health issues</p><p class="mt-1 text-2xl font-semibold">{{ summary.issues }}</p></div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/20"><p class="text-xs text-red-700 dark:text-red-300">Critical issues</p><p class="mt-1 text-2xl font-semibold text-red-700 dark:text-red-300">{{ summary.critical }}</p></div>
        </div>

        <section class="rounded-xl border bg-card">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <div class="flex items-center gap-2"><ShieldAlert class="size-4 text-amber-600" /><h2 class="font-semibold">Health issues</h2></div>
                <span class="text-xs text-muted-foreground">Sửa lỗi sẽ yêu cầu 2FA và được audit.</span>
            </div>
            <div v-if="!issues.length" class="flex items-center gap-2 p-6 text-sm text-emerald-700"><CheckCircle2 class="size-4" /> Không phát hiện lỗi cấu hình tenant/branch.</div>
            <div v-else class="divide-y">
                <div v-for="issue in issues" :key="issue.id" class="space-y-3 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex gap-3">
                            <AlertTriangle :class="issue.severity === 'critical' ? 'mt-0.5 size-4 text-red-600' : 'mt-0.5 size-4 text-amber-600'" />
                            <div><p class="font-medium">{{ issue.title }}</p><p class="text-sm text-muted-foreground">{{ issue.message }}</p><p class="mt-1 text-xs text-muted-foreground">{{ issue.tenant_name }}<span v-if="issue.branch_name"> · {{ issue.branch_name }}</span><span v-if="issue.user_email"> · {{ issue.user_email }}</span></p></div>
                        </div>
                        <span class="rounded-full bg-muted px-2 py-1 text-xs">{{ issueLabel[issue.type] ?? issue.type }}</span>
                    </div>

                    <div v-if="issue.type === 'manager_without_branch' || issue.type === 'branch_without_manager'" class="flex flex-wrap items-center gap-2 pl-7">
                        <select v-if="!issue.branch_id" v-model="branchSelection[issue.id]" class="h-9 rounded-md border bg-background px-2 text-sm"><option value="">Chọn branch</option><option v-for="branch in activeBranchesFor(issue.tenant_id)" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select>
                        <select v-model="managerSelection[issue.id]" class="h-9 rounded-md border bg-background px-2 text-sm"><option value="">Chọn manager</option><option v-for="candidate in managersFor(issue.tenant_id)" :key="candidate.id" :value="String(candidate.id)">{{ candidate.name }} · {{ candidate.email }}</option></select>
                        <button class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50" :disabled="busy === issue.id" @click="repairManager(issue)">Gán manager</button>
                    </div>

                    <div v-else-if="issue.type === 'tenant_without_owner'" class="flex flex-wrap items-center gap-2 pl-7">
                        <select v-model="ownerSelection[issue.id]" class="h-9 rounded-md border bg-background px-2 text-sm"><option value="">Chọn owner</option><option v-for="candidate in ownersFor(issue.tenant_id)" :key="candidate.id" :value="String(candidate.id)">{{ candidate.name }} · {{ candidate.email }}</option></select>
                        <button class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50" :disabled="busy === issue.id" @click="repairOwner(issue)">Gán owner</button>
                    </div>

                    <div v-else-if="issue.type === 'wrong_assignment' || issue.type === 'inactive_branch_active_user'" class="flex flex-wrap items-center gap-2 pl-7">
                        <select v-model="branchSelection[issue.id]" class="h-9 rounded-md border bg-background px-2 text-sm"><option value="">Chọn branch active</option><option v-for="branch in activeBranchesFor(issue.tenant_id)" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option></select>
                        <button class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground disabled:opacity-50" :disabled="busy === issue.id" @click="repairBranch(issue)">Gán lại branch</button>
                        <button class="rounded-md border px-3 py-2 text-sm hover:bg-muted disabled:opacity-50" :disabled="busy === `${issue.id}:clear`" @click="clearBranch(issue)">Xóa assignment</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border bg-card">
            <div class="border-b px-4 py-3"><h2 class="font-semibold">Tenant lifecycle & feature flags</h2><p class="text-xs text-muted-foreground">Feature tenant override sẽ ghi đè plan; chọn “Theo plan” để hoàn tác.</p></div>
            <div class="divide-y">
                <div v-for="tenant in tenants" :key="tenant.id" class="space-y-3 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div><p class="font-medium">{{ tenant.name }} <span class="text-xs text-muted-foreground">({{ tenant.code }})</span></p><p class="text-xs text-muted-foreground">{{ tenant.plan }} · {{ tenant.branches_count }} branch · Owner: {{ tenant.owner ?? 'Chưa có' }}</p></div>
                        <select :value="tenant.lifecycle_status" class="h-9 rounded-md border bg-background px-2 text-sm" @change="updateLifecycle(tenant, ($event.target as HTMLSelectElement).value)"><option v-for="status in lifecycle_statuses" :key="status" :value="status">{{ lifecycleLabel(status) }}</option></select>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <div v-for="flag in feature_flags" :key="flag.key" class="flex items-center gap-1 rounded-lg border px-2 py-1 text-xs" :class="tenant.features[flag.key] ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-950/20' : 'bg-muted/30'"><button class="font-medium" @click="updateFeature(tenant, flag.key, !tenant.features[flag.key])">{{ flag.label }}: {{ tenant.features[flag.key] ? 'On' : 'Off' }}</button><button v-if="tenant.feature_overrides[flag.key] !== undefined" class="ml-1 text-muted-foreground underline" @click="updateFeature(tenant, flag.key, tenant.features[flag.key], 'inherit')">Theo plan</button></div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
