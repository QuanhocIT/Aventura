<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    CheckCircle2,
    GitBranch,
    RefreshCw,
    Settings2,
    ShieldAlert,
    UserRoundCog,
} from 'lucide-vue-next';
import { reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    PageHeader,
    SectionCard,
    StatCard,
    StatusBadge,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
    props.branches.filter(
        (branch) => branch.tenant_id === tenantId && branch.status === 'active',
    );

const managersFor = (tenantId: number) =>
    props.manager_candidates.filter(
        (candidate) => candidate.tenant_id === tenantId,
    );

const ownersFor = (tenantId: number) =>
    props.owner_candidates.filter(
        (candidate) => candidate.tenant_id === tenantId,
    );

const lifecycleLabel = (status: string) =>
    ({
        active: 'Đang hoạt động',
        suspended: 'Tạm ngưng',
        archived: 'Đã lưu trữ',
        sandbox: 'Môi trường thử nghiệm',
    })[status] ?? status;

const issueLabel: Record<string, string> = {
    manager_without_branch: 'Quản lý chưa có chi nhánh',
    branch_without_manager: 'Chi nhánh chưa có quản lý hợp lệ',
    tenant_without_owner: 'Nhà hàng chưa có chủ tài khoản hợp lệ',
    wrong_assignment: 'Gán sai nhà hàng/chi nhánh',
    inactive_branch_active_user:
        'Chi nhánh ngừng hoạt động còn tài khoản đang dùng',
};

const severityLabel: Record<string, string> = {
    critical: 'Nghiêm trọng',
    warning: 'Cảnh báo',
};

function submit(
    payload: Record<string, unknown>,
    key: string,
    message: string,
) {
    busy.value = key;
    router.post('/super-admin/tenant-health/repair', payload, {
        preserveScroll: true,
        onSuccess: () => toast.success(message),
        onError: (errors: Record<string, string>) =>
            toast.error(
                Object.values(errors)[0] ?? 'Không thể thực hiện thao tác.',
            ),
        onFinish: () => {
            busy.value = null;
        },
    });
}

function repairManager(issue: Issue) {
    const managerId = managerSelection[issue.id];
    const branchId = issue.branch_id ?? branchSelection[issue.id];

    if (!managerId || !branchId) {
        toast.error('Vui lòng chọn quản lý và chi nhánh.');
        return;
    }

    submit(
        {
            action: 'assign_manager',
            tenant_id: issue.tenant_id,
            branch_id: Number(branchId),
            user_id: Number(managerId),
        },
        issue.id,
        'Đã gán quản lý cho chi nhánh.',
    );
}

function repairOwner(issue: Issue) {
    const userId = ownerSelection[issue.id];

    if (!userId) {
        toast.error('Vui lòng chọn chủ tài khoản.');
        return;
    }

    submit(
        {
            action: 'assign_owner',
            tenant_id: issue.tenant_id,
            user_id: Number(userId),
        },
        issue.id,
        'Đã gán chủ tài khoản cho nhà hàng.',
    );
}

function repairBranch(issue: Issue) {
    const branchId = branchSelection[issue.id];

    if (!branchId || !issue.user_id) {
        toast.error('Vui lòng chọn chi nhánh hợp lệ.');
        return;
    }

    submit(
        {
            action: 'reassign_branch',
            tenant_id: issue.tenant_id,
            branch_id: Number(branchId),
            user_id: issue.user_id,
        },
        issue.id,
        'Đã đồng bộ gán chi nhánh.',
    );
}

function clearBranch(issue: Issue) {
    if (!issue.user_id) {
        return;
    }

    submit(
        { action: 'clear_branch', user_id: issue.user_id },
        `${issue.id}:clear`,
        'Đã xóa gán chi nhánh lỗi.',
    );
}

function updateLifecycle(tenant: Tenant, lifecycleStatus: string) {
    submit(
        {
            action: 'set_lifecycle',
            tenant_id: tenant.id,
            lifecycle_status: lifecycleStatus,
        },
        `lifecycle:${tenant.id}`,
        'Đã cập nhật vòng đời nhà hàng.',
    );
}

function updateFeature(
    tenant: Tenant,
    feature: string,
    enabled: boolean,
    mode = 'override',
) {
    submit(
        {
            action: 'set_feature_flag',
            tenant_id: tenant.id,
            feature,
            enabled,
            mode,
        },
        `feature:${tenant.id}:${feature}`,
        'Đã cập nhật cờ tính năng.',
    );
}
</script>

<template>
    <Head title="Sức khỏe nhà hàng & chi nhánh" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Sức khỏe nhà hàng & chi nhánh"
            subtitle="Phát hiện và sửa các lỗi về chủ tài khoản, quản lý, chi nhánh và cờ tính năng."
            :icon="ShieldAlert"
        >
            <template #actions>
                <Button
                    variant="outline"
                    class="gap-2"
                    @click="router.reload()"
                >
                    <RefreshCw class="size-4" />
                    Làm mới
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Nhà hàng"
                :value="summary.tenants"
                :icon="Building2"
                color="sky"
            />
            <StatCard
                label="Chi nhánh"
                :value="summary.branches"
                :icon="GitBranch"
                color="violet"
            />
            <StatCard
                label="Lỗi cấu hình"
                :value="summary.issues"
                :icon="Settings2"
                color="amber"
            />
            <StatCard
                label="Lỗi nghiêm trọng"
                :value="summary.critical"
                :icon="AlertTriangle"
                color="rose"
            />
        </div>

        <SectionCard accent-color="rose">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 text-base font-bold">
                        <ShieldAlert class="size-4 text-rose-500" />
                        Lỗi cần xử lý
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Các thao tác sửa sẽ yêu cầu xác thực 2FA và được ghi vào
                        nhật ký hệ thống.
                    </p>
                </div>
                <StatusBadge status="critical" size="sm">
                    {{ summary.issues }} mục cần kiểm tra
                </StatusBadge>
            </div>

            <div
                v-if="!issues.length"
                class="flex items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-5 text-sm text-emerald-600 dark:text-emerald-400"
            >
                <CheckCircle2 class="size-4" />
                Không phát hiện lỗi cấu hình nhà hàng hoặc chi nhánh.
            </div>

            <div
                v-else
                class="overflow-hidden rounded-2xl border border-border/60 bg-card/70"
            >
                <div
                    v-for="issue in issues"
                    :key="issue.id"
                    class="space-y-3 border-b border-border/50 p-5 last:border-0"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="flex min-w-0 gap-3">
                            <AlertTriangle
                                :class="
                                    issue.severity === 'critical'
                                        ? 'mt-0.5 size-4 shrink-0 text-rose-500'
                                        : 'mt-0.5 size-4 shrink-0 text-amber-500'
                                "
                            />
                            <div class="min-w-0">
                                <p class="font-semibold">{{ issue.title }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ issue.message }}
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    {{ issue.tenant_name }}
                                    <span v-if="issue.branch_name">
                                        · {{ issue.branch_name }}</span
                                    >
                                    <span v-if="issue.user_email">
                                        · {{ issue.user_email }}</span
                                    >
                                </p>
                            </div>
                        </div>
                        <StatusBadge :status="issue.severity" size="sm">
                            {{ severityLabel[issue.severity] }}
                        </StatusBadge>
                    </div>

                    <div
                        v-if="
                            issue.type === 'manager_without_branch' ||
                            issue.type === 'branch_without_manager'
                        "
                        class="flex flex-wrap items-center gap-2 pl-7"
                    >
                        <select
                            v-if="!issue.branch_id"
                            v-model="branchSelection[issue.id]"
                            class="h-9 min-w-48 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Chọn chi nhánh</option>
                            <option
                                v-for="branch in activeBranchesFor(
                                    issue.tenant_id,
                                )"
                                :key="branch.id"
                                :value="String(branch.id)"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                        <select
                            v-model="managerSelection[issue.id]"
                            class="h-9 min-w-56 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Chọn quản lý</option>
                            <option
                                v-for="candidate in managersFor(
                                    issue.tenant_id,
                                )"
                                :key="candidate.id"
                                :value="String(candidate.id)"
                            >
                                {{ candidate.name }} · {{ candidate.email }}
                            </option>
                        </select>
                        <Button
                            size="sm"
                            :disabled="busy === issue.id"
                            @click="repairManager(issue)"
                        >
                            <UserRoundCog class="mr-1.5 size-4" />
                            Gán quản lý
                        </Button>
                    </div>

                    <div
                        v-else-if="issue.type === 'tenant_without_owner'"
                        class="flex flex-wrap items-center gap-2 pl-7"
                    >
                        <select
                            v-model="ownerSelection[issue.id]"
                            class="h-9 min-w-64 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">Chọn chủ tài khoản</option>
                            <option
                                v-for="candidate in ownersFor(issue.tenant_id)"
                                :key="candidate.id"
                                :value="String(candidate.id)"
                            >
                                {{ candidate.name }} · {{ candidate.email }}
                            </option>
                        </select>
                        <Button
                            size="sm"
                            :disabled="busy === issue.id"
                            @click="repairOwner(issue)"
                        >
                            Gán chủ tài khoản
                        </Button>
                    </div>

                    <div
                        v-else-if="
                            issue.type === 'wrong_assignment' ||
                            issue.type === 'inactive_branch_active_user'
                        "
                        class="flex flex-wrap items-center gap-2 pl-7"
                    >
                        <select
                            v-model="branchSelection[issue.id]"
                            class="h-9 min-w-56 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">
                                Chọn chi nhánh đang hoạt động
                            </option>
                            <option
                                v-for="branch in activeBranchesFor(
                                    issue.tenant_id,
                                )"
                                :key="branch.id"
                                :value="String(branch.id)"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                        <Button
                            size="sm"
                            :disabled="busy === issue.id"
                            @click="repairBranch(issue)"
                        >
                            Gán lại chi nhánh
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="busy === `${issue.id}:clear`"
                            @click="clearBranch(issue)"
                        >
                            Xóa gán sai
                        </Button>
                    </div>
                </div>
            </div>
        </SectionCard>

        <SectionCard accent-color="sky">
            <div>
                <h2 class="flex items-center gap-2 text-base font-bold">
                    <Settings2 class="size-4 text-sky-500" />
                    Vòng đời và cờ tính năng
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Cờ tính năng ghi đè sẽ ưu tiên hơn gói dịch vụ; chọn “Theo
                    gói” để khôi phục cấu hình mặc định.
                </p>
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
                <Card
                    v-for="tenant in tenants"
                    :key="tenant.id"
                    class="overflow-hidden"
                >
                    <CardHeader class="border-b border-border/50 pb-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <CardTitle class="text-base">{{
                                    tenant.name
                                }}</CardTitle>
                                <CardDescription class="mt-1">
                                    {{ tenant.code }} · {{ tenant.plan }} ·
                                    {{ tenant.branches_count }} chi nhánh
                                </CardDescription>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Chủ tài khoản:
                                    {{ tenant.owner ?? 'Chưa có' }}
                                </p>
                            </div>
                            <select
                                :value="tenant.lifecycle_status"
                                class="h-9 rounded-lg border bg-background px-3 text-sm"
                                @change="
                                    updateLifecycle(
                                        tenant,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option
                                    v-for="status in lifecycle_statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ lifecycleLabel(status) }}
                                </option>
                            </select>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2 pt-4">
                        <div
                            v-for="flag in feature_flags"
                            :key="flag.key"
                            class="flex items-center gap-1 rounded-lg border px-2 py-1"
                            :class="
                                tenant.features[flag.key]
                                    ? 'border-emerald-500/25 bg-emerald-500/5'
                                    : 'bg-muted/30'
                            "
                        >
                            <button
                                class="text-xs font-medium transition-colors hover:text-primary"
                                @click="
                                    updateFeature(
                                        tenant,
                                        flag.key,
                                        !tenant.features[flag.key],
                                    )
                                "
                            >
                                {{ flag.label }}:
                                {{ tenant.features[flag.key] ? 'Bật' : 'Tắt' }}
                            </button>
                            <button
                                v-if="
                                    tenant.feature_overrides[flag.key] !==
                                    undefined
                                "
                                class="ml-1 text-[11px] text-muted-foreground underline hover:text-foreground"
                                @click="
                                    updateFeature(
                                        tenant,
                                        flag.key,
                                        tenant.features[flag.key],
                                        'inherit',
                                    )
                                "
                            >
                                Theo gói
                            </button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </SectionCard>
    </div>
</template>
