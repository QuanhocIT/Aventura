import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export type BranchContextItem = {
    id: number;
    name: string;
};

function normalizeRoles(raw: unknown): string[] {
    if (Array.isArray(raw)) {
        return raw.map(String);
    }

    return Object.values((raw ?? {}) as Record<string, unknown>).map(String);
}

/**
 * The single frontend adapter for the server-side TenantContext.
 *
 * Branch switching is intentionally exposed as an action rather than as a
 * local watcher. This keeps the header, Dashboard shortcuts and custom POS
 * surfaces on the same POST endpoint and prevents stale local selectors.
 */
export function useBranchContext() {
    const page = usePage();
    const tenant = computed(() => (page.props as any).tenant ?? null);
    const roles = computed(() => normalizeRoles((page.props as any).roles));
    const isOwner = computed(() => roles.value.includes('owner'));
    const branches = computed<BranchContextItem[]>(
        () => tenant.value?.branches ?? [],
    );
    const activeBranchId = computed<number | null>(() => {
        const value = tenant.value?.active_branch_id;

        return value === null || value === undefined || value === ''
            ? null
            : Number(value);
    });
    const activeBranch = computed(
        () =>
            branches.value.find(
                (branch) => branch.id === activeBranchId.value,
            ) ?? null,
    );
    const isAllBranches = computed(() => tenant.value?.scope === 'all');
    const currentBranchName = computed(() =>
        isAllBranches.value
            ? 'Toàn chuỗi'
            : (activeBranch.value?.name ?? 'Chi nhánh chưa xác định'),
    );

    const canSelectBranch = computed(
        () =>
            (isOwner.value ||
                roles.value.includes('operations_inspector') ||
                roles.value.includes('compliance_auditor') ||
                roles.value.includes('accountant') ||
                roles.value.includes('super_admin')) &&
            !roles.value.includes('warehouse_manager'),
    );

    const switchBranch = (branchId: number | null) => {
        if (branchId === null && !canSelectBranch.value) {
            return;
        }

        router.post(
            '/branch/switch',
            branchId === null ? { scope: 'all' } : { branch_id: branchId },
            {
                preserveScroll: true,
            },
        );
    };

    return {
        tenant,
        roles,
        isOwner,
        canSelectBranch,
        branches,
        activeBranchId,
        activeBranch,
        isAllBranches,
        currentBranchName,
        switchBranch,
    };
}
