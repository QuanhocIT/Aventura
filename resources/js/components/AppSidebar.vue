<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Building2,
    BadgeDollarSign,
    Users,
    FileSearch2,
    Headset,
    UtensilsCrossed,
    ShoppingCart,
    Package,
    ChefHat,
    BarChart3,
    Clock,
    Wallet,
    MessageSquare,
    BookOpen,
    FolderGit2,
    Tag,
    Truck,
    UserCheck,
    CalendarDays,
    ScrollText,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import SubscriptionWidget from '@/components/SubscriptionWidget.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { dashboard as superAdminDashboard } from '@/routes/superadmin';
import type { NavItem } from '@/types';

const page = usePage();

const tenant = computed(() => page.props.tenant as any);

const isSubscriptionActive = computed(() => {
    if (isSuperAdmin.value) return true;
    if (!tenant.value) return true;
    return tenant.value.status === 'active' || tenant.value.status === 'trial';
});

const roles = computed(() => {
    const raw = page.props.roles ?? [];
    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});

const hasRole = (...roleNames: string[]) =>
    roles.value.some((r: string) => roleNames.includes(r));

const isSuperAdmin = computed(() => hasRole('admin', 'super_admin'));
const isOwner = computed(() => hasRole('owner'));
const isManager = computed(() => hasRole('manager'));
const isCashier = computed(() => hasRole('cashier'));
const isKitchen = computed(() => hasRole('kitchen'));
const isInventory = computed(() => hasRole('inventory_staff'));
const isCustomer = computed(() => hasRole('customer'));

// ─── SUPER ADMIN MENU ────────────────────────────────────────────────────────
const superAdminNav: NavItem[] = [
    { title: 'Dashboard', href: superAdminDashboard().url, icon: LayoutGrid },
    { title: 'Nhà hàng', href: '/super-admin/restaurants', icon: Building2 },
    { title: 'Gói dịch vụ', href: '/super-admin/plans', icon: BadgeDollarSign },
    { title: 'Tài khoản', href: '/super-admin/accounts', icon: Users },
    {
        title: 'Billing Center',
        href: '/super-admin/billing',
        icon: BadgeDollarSign,
    },
    { title: 'Audit Log', href: '/super-admin/audit-logs', icon: FileSearch2 },
    { title: 'DevOps & Support', href: '/super-admin/support', icon: Headset },
];

// ─── OWNER MENU ───────────────────────────────────────────────────────────────
const ownerNav: NavItem[] = [
    { title: 'Tổng quan', href: '/dashboard', icon: LayoutGrid },
    { title: 'Quản lý đơn hàng', href: '/orders', icon: ShoppingCart },
    { title: 'Thực đơn & Món', href: '/products', icon: UtensilsCrossed },
    { title: 'Kho nguyên liệu', href: '/inventory', icon: Package },
    { title: 'Nhà cung cấp', href: '/suppliers', icon: Truck },
    { title: 'Nhân sự', href: '/employees', icon: UserCheck },
    { title: 'Chấm công & Lịch', href: '/schedules', icon: CalendarDays },
    { title: 'Bảng lương', href: '/salaries', icon: Wallet },
    { title: 'Khách hàng', href: '/customers', icon: Users },
    { title: 'Khuyến mãi', href: '/promotions', icon: Tag },
    { title: 'Báo cáo & AI', href: '/reports', icon: BarChart3 },
    { title: 'Sơ đồ bàn', href: '/tables', icon: Building2 },
    { title: 'Audit Log', href: '/audit-logs', icon: ScrollText },
    { title: 'Liên hệ & Hỗ trợ', href: '/support', icon: Headset },
];

// ─── MANAGER MENU ─────────────────────────────────────────────────────────────
const managerNav: NavItem[] = [
    { title: 'Tổng quan', href: '/dashboard', icon: LayoutGrid },
    { title: 'Đơn hàng hôm nay', href: '/orders', icon: ShoppingCart },
    { title: 'Nhân sự', href: '/employees', icon: UserCheck },
    { title: 'Chấm công & Lịch', href: '/schedules', icon: CalendarDays },
    { title: 'Bảng lương', href: '/salaries', icon: Wallet },
    { title: 'Khuyến mãi', href: '/promotions', icon: Tag },
    { title: 'Phản hồi KH', href: '/feedback', icon: MessageSquare },
    { title: 'Báo cáo doanh thu', href: '/reports', icon: BarChart3 },
    { title: 'Vi phạm nội bộ', href: '/violations', icon: FileSearch2 },
    { title: 'Liên hệ & Hỗ trợ', href: '/support', icon: Headset },
];

// ─── CASHIER MENU ─────────────────────────────────────────────────────────────
const cashierNav: NavItem[] = [
    { title: 'Tạo đơn hàng', href: '/orders/create', icon: ShoppingCart },
    { title: 'Sơ đồ bàn', href: '/tables', icon: Building2 },
    { title: 'Lịch sử đơn', href: '/orders', icon: ScrollText },
    { title: 'Doanh thu ca', href: '/reports/shift', icon: Wallet },
    { title: 'Lịch làm việc', href: '/schedules', icon: CalendarDays },
];

// ─── KITCHEN MENU ─────────────────────────────────────────────────────────────
const kitchenNav: NavItem[] = [
    { title: 'Màn hình bếp', href: '/kitchen', icon: ChefHat },
    { title: 'Lịch làm việc', href: '/schedules', icon: CalendarDays },
];

// ─── INVENTORY STAFF MENU ─────────────────────────────────────────────────────
const inventoryNav: NavItem[] = [
    { title: 'Tồn kho', href: '/inventory', icon: Package },
    { title: 'Nhập kho', href: '/inventory/receive', icon: Truck },
    {
        title: 'Lịch sử giao dịch',
        href: '/inventory/transactions',
        icon: Clock,
    },
    { title: 'Nhà cung cấp', href: '/suppliers', icon: Truck },
    { title: 'Lịch làm việc', href: '/schedules', icon: CalendarDays },
];

// ─── CUSTOMER MENU ────────────────────────────────────────────────────────────
const customerNav: NavItem[] = [
    { title: 'Thực đơn', href: '/menu', icon: UtensilsCrossed },
    { title: 'Đơn hàng của tôi', href: '/my-orders', icon: ShoppingCart },
    { title: 'Khuyến mãi', href: '/promotions', icon: Tag },
    { title: 'Phản hồi', href: '/feedback', icon: MessageSquare },
];

// ─── Chọn menu dựa trên role và hạn gói ──────────────────────────────────────────
const mainNavItems = computed<NavItem[]>(() => {
    if (isSuperAdmin.value) return superAdminNav;

    // Nếu gói hết hạn / bị khóa, giới hạn chỉ cho phép xem Tổng quan
    if (!isSubscriptionActive.value) {
        return [{ title: 'Tổng quan', href: '/dashboard', icon: LayoutGrid }];
    }

    if (isOwner.value) return ownerNav;
    if (isManager.value) return managerNav;
    if (isCashier.value) return cashierNav;
    if (isKitchen.value) return kitchenNav;
    if (isInventory.value) return inventoryNav;
    if (isCustomer.value) return customerNav;
    return [];
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain v-if="mainNavItems.length" :items="mainNavItems" />
            <div v-else class="px-4 py-6 text-xs text-muted-foreground">
                Không có menu khả dụng cho tài khoản này.
            </div>

            <!-- Widget hạn ngạch & dùng thử chuyên nghiệp -->
            <SubscriptionWidget v-if="tenant && !isSuperAdmin" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
