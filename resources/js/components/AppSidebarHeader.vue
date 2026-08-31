<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle, BookOpen, Star } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppearanceToggleInline from '@/components/AppearanceToggleInline.vue';
import BackButton from '@/components/BackButton.vue';
import BranchContextSelector from '@/components/BranchContextSelector.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationCenter from '@/components/NotificationCenter.vue';
import PlatformFeedbackModal from '@/components/PlatformFeedbackModal.vue';
import PolicyViewerModal from '@/components/PolicyViewerModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useBranchContext } from '@/composables/useBranchContext';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem, User } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => (page.props.auth?.user as User | null) ?? null);

const navItems = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Sản phẩm', href: '/products' },
    { label: 'Kho', href: '/inventory' },
    { label: 'Nhân viên', href: '/employees' },
    { label: 'Hỗ trợ', href: '/support' },
];

const warehouseStaffNavItems = [
    { label: 'Tác vụ hôm nay', href: '/inventory/staff-portal' },
    { label: 'Kho', href: '/inventory/central-warehouse' },
    { label: 'Nhận hàng', href: '/inventory/central-warehouse/receiving' },
    { label: 'Logistics', href: '/inventory/delivery-manifests' },
    { label: 'Hỗ trợ', href: '/support' },
];

const superAdminNavItems = [
    { label: 'Tổng quan SaaS', href: '/super-admin/dashboard' },
    { label: 'Nhà hàng & Tenants', href: '/super-admin/restaurants' },
    { label: 'Doanh thu & Cước phí', href: '/super-admin/revenue' },
    { label: 'Sức khỏe hệ thống', href: '/super-admin/service-monitor' },
    { label: 'Hỗ trợ & Ticket', href: '/super-admin/support' },
    { label: 'Cài đặt SaaS', href: '/super-admin/settings' },
];

const isSuperAdminRoute = computed(() => page.url.startsWith('/super-admin'));

const roles = computed(() => {
    const raw = page.props.roles ?? [];

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});

const isSuperAdmin = computed(() =>
    roles.value.some((role) =>
        [
            'super_admin',
            'system_admin',
            'billing_admin',
            'support_specialist',
        ].includes(role),
    ),
);
void isSuperAdmin.value;
const isOwner = computed(() => roles.value.includes('owner'));
const isEmployee = computed(() =>
    roles.value.some((role) =>
        ['cashier', 'waiter', 'kitchen', 'inventory_staff', 'shipper'].includes(
            role,
        ),
    ),
);
const isWarehouseStaff = computed(() =>
    roles.value.includes('warehouse_staff'),
);
const isWarehouseManager = computed(() =>
    roles.value.includes('warehouse_manager'),
);
const { isAllBranches } = useBranchContext();

const showFeedbackModal = ref(false);
const showPolicyModal = ref(false);
</script>

<template>
    <header
        class="sticky top-0 z-40 flex h-16 shrink-0 items-center justify-between border-b border-sidebar-border/70 bg-background/95 px-6 shadow-sm backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 supports-[backdrop-filter]:bg-background/80 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <BackButton
                variant="ghost"
                size="sm"
                :show-label="false"
                class="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
                label="Quay lại trang trước"
            />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
            <nav
                v-else-if="user && isSuperAdminRoute"
                class="hidden shrink-0 items-center gap-0.5 md:flex"
            >
                <Link
                    v-for="item in superAdminNavItems"
                    :key="item.href"
                    :href="item.href"
                    class="shrink-0 rounded-md px-3 py-1.5 text-sm whitespace-nowrap transition-colors"
                    :class="
                        page.url.startsWith(item.href)
                            ? 'bg-muted font-medium text-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>
            <nav
                v-else-if="
                    user &&
                    !isSuperAdminRoute &&
                    !isEmployee &&
                    !isWarehouseStaff &&
                    !isWarehouseManager
                "
                class="hidden shrink-0 items-center gap-0.5 md:flex"
            >
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="shrink-0 rounded-md px-3 py-1.5 text-sm whitespace-nowrap transition-colors"
                    :class="
                        page.url.startsWith(item.href)
                            ? 'bg-muted font-medium text-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>
            <nav
                v-else-if="
                    user &&
                    !isSuperAdminRoute &&
                    (isWarehouseStaff || isWarehouseManager)
                "
                class="hidden shrink-0 items-center gap-0.5 md:flex"
            >
                <Link
                    v-for="item in warehouseStaffNavItems"
                    :key="item.href"
                    :href="item.href"
                    class="shrink-0 rounded-md px-3 py-1.5 text-sm whitespace-nowrap transition-colors"
                    :class="
                        page.url.startsWith(item.href)
                            ? 'bg-muted font-medium text-foreground'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>
        </div>

        <div class="flex items-center gap-4">
            <!-- Nút Tra cứu nhanh Quy Định & Tiêu Chuẩn dành cho nhân viên (Ẩn ở tài khoản Chủ doanh nghiệp & SuperAdmin) -->
            <button
                v-if="user && !isOwner && !isSuperAdminRoute"
                @click="showPolicyModal = true"
                class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-indigo-500/30 bg-indigo-500/10 px-2.5 py-1.5 text-xs font-bold text-indigo-700 shadow-2xs transition-all hover:scale-[1.02] hover:bg-indigo-500/20 dark:text-indigo-300"
                title="Tra cứu Bộ Quy Định & Tiêu Chuẩn Vận Hành Nhà Hàng"
            >
                <BookOpen
                    class="size-3.5 text-indigo-600 dark:text-indigo-400"
                />
                <span class="hidden sm:inline">📜 Quy Định & Tiêu Chuẩn</span>
            </button>

            <!-- The only global branch selector. Non-owners see a read-only context. -->
            <BranchContextSelector v-if="!isEmployee && !isSuperAdminRoute" class="mr-2" />

            <AppearanceToggleInline />

            <!-- SaaS Service Feedback button (Dành riêng cho Chủ doanh nghiệp / Tenant users) -->
            <button
                v-if="user && isOwner && !isSuperAdminRoute"
                @click="showFeedbackModal = true"
                class="flex cursor-pointer items-center gap-1.5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-2.5 py-1.5 text-xs font-bold text-amber-600 shadow-2xs transition-all hover:scale-[1.02] hover:bg-amber-500/20 dark:text-amber-400"
                title="Gửi đánh giá gói dịch vụ & hệ thống Aventura"
            >
                <Star class="size-3.5 fill-amber-400 text-amber-400" />
                <span class="hidden sm:inline">Đánh giá dịch vụ</span>
            </button>

            <!-- Notification Center -->
            <NotificationCenter v-if="user" />

            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <button
                        class="cursor-pointer rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <Avatar class="h-9 w-9 border border-border">
                            <AvatarImage
                                v-if="user.avatar"
                                :src="user.avatar"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent class="w-60" align="end" :side-offset="8">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>

    <div
        v-if="isAllBranches"
        class="mx-4 mt-2 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm leading-5 font-medium text-amber-800 md:mx-6 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
        role="status"
    >
        <AlertTriangle
            class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
        />
        <span class="min-w-0 flex-1">
            <span class="font-semibold">Bạn đang xem dữ liệu:</span>
            <strong class="ml-1">Toàn chuỗi.</strong>
            <span class="ml-1 font-normal"
                >Các số liệu đang được tổng hợp từ các chi nhánh.</span
            >
        </span>
    </div>

    <PlatformFeedbackModal v-if="isOwner" v-model:open="showFeedbackModal" />
    <PolicyViewerModal v-model:is-open="showPolicyModal" />
</template>
