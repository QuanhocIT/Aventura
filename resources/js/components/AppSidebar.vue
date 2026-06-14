<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Bot,
    Brain,
    Building2,
    BadgeDollarSign,
    ClipboardCheck,
    Image,
    Newspaper,
    Users,
    FileSearch2,
    Headset,
    UtensilsCrossed,
    Megaphone,
    ShoppingCart,
    Package,
    ChefHat,
    Receipt,
    BarChart3,
    Clock,
    Wallet,
    MessageSquare,
    BookOpen,
    FolderGit2,
    Tag,
    Truck,
    Route,
    UserCheck,
    CalendarDays,
    ScrollText,
    ShieldCheck,
    ShieldAlert,
    Gift,
    Activity,
    Settings,
    Crown,
    Trash2,
    Database,
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
const pendingApprovalCount = computed(() => (page.props.pendingApprovalCount as number) ?? 0);

const isSubscriptionActive = computed(() => {
    if (isSuperAdmin.value) {
return true;
}

    if (!tenant.value) {
return true;
}

    return tenant.value.status === 'active' || tenant.value.status === 'trial';
});

const roles = computed(() => {
    const raw = page.props.roles ?? [];

    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});

const hasRole = (...roleNames: string[]) =>
    roles.value.some((r: string) => roleNames.includes(r));

const isSuperAdmin  = computed(() => hasRole('super_admin'));
const isOwner       = computed(() => hasRole('owner'));
const isManager     = computed(() => hasRole('manager'));
const isCashier     = computed(() => hasRole('cashier', 'waiter'));
const isKitchen     = computed(() => hasRole('kitchen'));
const isInventory   = computed(() => hasRole('inventory_staff'));
const isCustomer    = computed(() => hasRole('customer'));
const isSupplier    = computed(() => hasRole('supplier'));
const isShipper     = computed(() => hasRole('shipper'));

// Lấy danh sách permissions từ Inertia shared state
const permissions = computed(() => {
    const authUser = page.props.auth?.user as any;
    return authUser?.permissions ?? [];
});

// Kiểm tra quyền Spatie
const can = (permission: string) => {
    if (isSuperAdmin.value) return true;
    return permissions.value.includes(permission);
};

// ─── SUPER ADMIN MENU ────────────────────────────────────────────────────────
const superAdminNav: NavItem[] = [
    { title: 'Dashboard',      href: superAdminDashboard().url, icon: LayoutGrid },
    { title: 'Nhà hàng',       href: '/super-admin/restaurants', icon: Building2 },
    { title: 'Gói dịch vụ',   href: '/super-admin/plans',       icon: BadgeDollarSign },
    { title: 'Tài khoản',      href: '/super-admin/accounts',    icon: Users },
    { title: 'Billing Center', href: '/super-admin/billing',     icon: BadgeDollarSign },
    { title: 'Mã giảm giá',    href: '/super-admin/coupons',     icon: Tag },
    { title: 'Hoa hồng & Rút tiền', href: '/super-admin/referrals', icon: Crown },
    { title: 'Banner & Slideshow', href: '/super-admin/banners', icon: Image },
    { title: 'Dọn dẹp rác',    href: '/super-admin/garbage-collector', icon: Trash2 },
    { title: 'Sao lưu & Tối ưu DB', href: '/super-admin/backup-maintenance', icon: Database },
    { title: 'Meilisearch Console', href: '/super-admin/meilisearch-console', icon: Database },
    { title: 'Giám sát Dịch vụ', href: '/super-admin/service-monitor', icon: Activity },
    { title: 'Audit Log',      href: '/super-admin/audit-logs',  icon: FileSearch2 },
    { title: 'DevOps & Support', href: '/super-admin/support',  icon: Headset },
    { title: 'Dự đoán rời bỏ', href: '/super-admin/churn-prediction', icon: ShieldAlert },
    { title: 'Chatbot AI',      href: '/super-admin/chatbot',             icon: Bot },
    { title: 'Chatbot Diagnostics', href: '/super-admin/chatbot-diagnostics', icon: Brain },
    { title: 'Tin tức',         href: '/super-admin/news',      icon: Newspaper },
    { title: 'Chiến dịch Quảng bá', href: '/super-admin/campaigns', icon: Megaphone },
    { title: 'Cấu hình hệ thống', href: '/super-admin/settings', icon: Settings },
];

// ─── OWNER MENU ───────────────────────────────────────────────────────────────
const ownerNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Tổng quan',        href: '/dashboard',              icon: LayoutGrid },
        { title: 'Quản lý đơn hàng', href: '/orders',                 icon: ShoppingCart },
        { title: 'Thực đơn & Món',   href: '/products',               icon: UtensilsCrossed },
        { title: 'Kho nguyên liệu',  href: '/inventory',              icon: Package },
        { title: 'Nhà cung cấp',     href: '/suppliers',              icon: Truck },
        { title: 'Đấu thầu RFP',     href: '/rfps',                   icon: ScrollText },
        { title: 'Giao hàng',        href: '/delivery',               icon: Route },
        { title: 'Nhân sự',          href: '/employees',              icon: UserCheck },
        { title: 'Chấm công & Lịch', href: '/schedules',              icon: CalendarDays },
        { title: 'Bảng lương',       href: '/salaries',               icon: Wallet, permission: 'manage_salary' },
        { title: 'Phê duyệt',        href: '/approvals',              icon: ShieldCheck, badge: pendingApprovalCount.value },
        { title: 'Khách hàng',       href: '/customers',              icon: Users },
        { title: 'Khuyến mãi',       href: '/promotions',             icon: Tag },
        { title: 'Hóa đơn & Gói cước', href: '/billing/history',      icon: Receipt },
        { title: 'Giới thiệu & Hoa hồng', href: '/settings/referrals', icon: Gift },
        { title: 'Phản hồi KH',      href: '/feedback',               icon: MessageSquare, permission: 'manage_feedback' },
        { title: 'Báo cáo & AI',     href: '/reports',                icon: BarChart3, permission: 'view_report' },
        { title: 'Trợ lý AI Chiến lược', href: '/ai-advisor',             icon: Bot, permission: 'view_report' },
        { title: 'Chốt ca',          href: '/shift-closings',         icon: ClipboardCheck },
        { title: 'Kiểm toán Gian lận', href: '/fraud',                icon: ShieldAlert },
        { title: 'Vi phạm nội bộ',   href: '/violations',             icon: FileSearch2, permission: 'view_violations' },
        { title: 'Sơ đồ bàn',        href: '/tables',                 icon: Building2 },
        { title: 'Audit Log',        href: '/audit-logs',             icon: ScrollText, permission: 'view_audit_log' },
        { title: 'Tin tức',          href: '/tin-tuc',                icon: Newspaper },
        { title: 'Liên hệ & Hỗ trợ', href: '/support',                icon: Headset },
    ];

    return nav.filter(item => {
        if (isManager.value) {
            if (item.href === '/fraud' || item.href === '/audit-logs') {
                return false;
            }
        }
        if (item.permission) {
            return can(item.permission);
        }
        return true;
    });
});

// ─── MANAGER MENU ─────────────────────────────────────────────────────────────
const managerNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Tổng quan',        href: '/dashboard',              icon: LayoutGrid },
        { title: 'Đơn hàng hôm nay', href: '/orders',                 icon: ShoppingCart },
        { title: 'Kho nguyên liệu',  href: '/inventory',              icon: Package },
        { title: 'Đấu thầu RFP',     href: '/rfps',                   icon: ScrollText },
        { title: 'Giao hàng',        href: '/delivery',               icon: Route },
        { title: 'Nhân sự',          href: '/employees',              icon: UserCheck },
        { title: 'Chấm công & Lịch', href: '/schedules',              icon: CalendarDays },
        { title: 'Bảng lương',       href: '/salaries',               icon: Wallet, permission: 'manage_salary' },
        { title: 'Khách hàng',       href: '/customers',              icon: Users },
        { title: 'Khuyến mãi',       href: '/promotions',             icon: Tag },
        { title: 'Hóa đơn & Gói cước', href: '/billing/history',      icon: Receipt },
        { title: 'Giới thiệu & Hoa hồng', href: '/settings/referrals', icon: Gift },
        { title: 'Phản hồi KH',      href: '/feedback',               icon: MessageSquare, permission: 'manage_feedback' },
        { title: 'Báo cáo doanh thu', href: '/reports',               icon: BarChart3, permission: 'view_report' },
        { title: 'Trợ lý AI Chiến lược', href: '/ai-advisor',             icon: Bot, permission: 'view_report' },
        { title: 'Chốt ca',          href: '/shift-closings',         icon: ClipboardCheck },
        { title: 'Kiểm toán Gian lận', href: '/fraud',                icon: ShieldAlert },
        { title: 'Vi phạm nội bộ',   href: '/violations',             icon: FileSearch2, permission: 'view_violations' },
        { title: 'Tin tức',          href: '/tin-tuc',                icon: Newspaper },
        { title: 'Liên hệ & Hỗ trợ', href: '/support',                icon: Headset },
    ];

    return nav.filter(item => {
        if (item.permission) {
            return can(item.permission);
        }
        return true;
    });
});

// ─── CASHIER MENU ─────────────────────────────────────────────────────────────
const cashierNav: NavItem[] = [
    { title: 'Trang chủ',       href: '/dashboard',               icon: LayoutGrid },
    { title: 'Lịch sử đơn',     href: '/orders',                  icon: ScrollText },
    { title: 'Doanh thu ca',     href: '/shift-closings',          icon: ClipboardCheck },
    { title: 'Lịch làm việc',   href: '/schedules',               icon: CalendarDays },
    { title: 'Tố cáo ẩn danh',   href: '/violations',              icon: ShieldAlert },
];

// ─── KITCHEN MENU ─────────────────────────────────────────────────────────────
const kitchenNav: NavItem[] = [
    { title: 'Trang chủ',       href: '/dashboard',               icon: LayoutGrid },
    { title: 'Lịch làm việc',   href: '/schedules',                icon: CalendarDays },
    { title: 'Doanh thu ca',     href: '/shift-closings',          icon: ClipboardCheck },
    { title: 'Lịch sử đơn',     href: '/orders',                  icon: ScrollText },
    { title: 'Tố cáo ẩn danh',   href: '/violations',              icon: ShieldAlert },
];

// ─── INVENTORY STAFF MENU ─────────────────────────────────────────────────────
const inventoryNav: NavItem[] = [
    { title: 'Tồn kho',         href: '/inventory',                icon: Package },
    { title: 'Nhập kho',        href: '/inventory/receive',        icon: Truck },
    { title: 'Lịch sử giao dịch', href: '/inventory/transactions', icon: Clock },
    { title: 'Nhà cung cấp',    href: '/suppliers',                icon: Truck },
    { title: 'Lịch làm việc',   href: '/schedules',                icon: CalendarDays },
];

// ─── CUSTOMER MENU ────────────────────────────────────────────────────────────
const customerNav: NavItem[] = [
    { title: 'Thực đơn',        href: '/menu',                     icon: UtensilsCrossed },
    { title: 'Đơn hàng của tôi', href: '/my-orders',               icon: ShoppingCart },
    { title: 'Khuyến mãi',      href: '/promotions',               icon: Tag },
    { title: 'Phản hồi',        href: '/feedback',                 icon: MessageSquare },
];

// ─── SHIPPER MENU ────────────────────────────────────────────────────────────
const shipperNav: NavItem[] = [
    { title: 'Giao hàng của tôi', href: '/delivery/shipper', icon: Route },
    { title: 'Lịch làm việc',    href: '/schedules',          icon: CalendarDays },
];

// ─── SUPPLIER PORTAL MENU ────────────────────────────────────────────────────
const supplierNav: NavItem[] = [
    { title: 'Tổng quan',           href: '/supplier/dashboard', icon: LayoutGrid },
    { title: 'Danh mục & Niêm yết',  href: '/supplier/catalog',   icon: Package },
    { title: 'Đơn đặt hàng (PO)',   href: '/supplier/orders',    icon: ShoppingCart },
    { title: 'Đấu thầu RFP',        href: '/supplier/rfps',      icon: ScrollText },
];

// ─── Chọn menu dựa trên role và hạn gói ──────────────────────────────────────────
const mainNavItems = computed<NavItem[]>(() => {
    if (isSuperAdmin.value) {
        return superAdminNav;
    }

    if (isSupplier.value) {
        return supplierNav;
    }

    if (isShipper.value) {
        return shipperNav;
    }
    
    // Nếu gói hết hạn / bị khóa, giới hạn chỉ cho phép xem Tổng quan
    if (!isSubscriptionActive.value) {
        return [
            { title: 'Tổng quan', href: '/dashboard', icon: LayoutGrid }
        ];
    }
    
    if (isOwner.value || isManager.value)      {
return ownerNav.value;
}

    if (isCashier.value)    {
return cashierNav;
}

    if (isKitchen.value)    {
return kitchenNav;
}

    if (isInventory.value)  {
return inventoryNav;
}

    if (isCustomer.value)   {
return customerNav;
}

    return [];
});

const footerNavItems: NavItem[] = [
    { title: 'Repository',    href: 'https://github.com/laravel/vue-starter-kit', icon: FolderGit2 },
    { title: 'Documentation', href: 'https://laravel.com/docs/starter-kits#vue',   icon: BookOpen },
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
            <SubscriptionWidget v-if="tenant && isOwner" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
