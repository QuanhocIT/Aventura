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
    Receipt,
    BarChart3,
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
    Globe,
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
import { useFeatureGate } from '@/composables/useFeatureGate';
import { dashboard } from '@/routes';
import { dashboard as superAdminDashboard } from '@/routes/superadmin';
import type { NavItem } from '@/types';

const { can: canFeature } = useFeatureGate();

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
const isSupplier    = computed(() => hasRole('supplier'));
const isShipper     = computed(() => hasRole('shipper'));

// Lấy danh sách permissions từ Inertia shared state
const permissions = computed(() => {
    const authUser = page.props.auth?.user as any;

    return authUser?.permissions ?? [];
});

// Kiểm tra quyền Spatie
const can = (permission: string) => {
    if (isSuperAdmin.value) {
        return true;
    }

    return permissions.value.includes(permission);
};

// ─── SUPER ADMIN MENU ────────────────────────────────────────────────────────
const canAdmin = (perm: string) =>
    hasRole('super_admin') || permissions.value.includes(perm);

const superAdminNav = computed<NavItem[]>(() => {
    const all: (NavItem & { perm?: string })[] = [
        // Shared — superadmin.dashboard.view
        { title: 'Dashboard',        href: superAdminDashboard().url, icon: LayoutGrid, perm: 'superadmin.dashboard.view' },
        { title: 'Nhà hàng',         href: '/super-admin/restaurants', icon: Building2, perm: 'superadmin.dashboard.view' },
        { title: 'Đơn hàng hệ thống', href: '/super-admin/orders', icon: ShoppingCart, perm: 'superadmin.dashboard.view' },
        { title: 'Doanh thu hệ thống', href: '/super-admin/revenue', icon: Wallet, perm: 'superadmin.dashboard.view' },
        { title: 'Khách hàng', href: '/super-admin/customers', icon: Users, perm: 'superadmin.dashboard.view' },
        { title: 'Feedback', href: '/super-admin/feedback', icon: MessageSquare, perm: 'superadmin.dashboard.view' },
        { title: 'Banner & Slideshow', href: '/super-admin/banners', icon: Image, perm: 'superadmin.dashboard.view' },
        { title: 'Tin tức',           href: '/super-admin/news',      icon: Newspaper, perm: 'superadmin.dashboard.view' },
        { title: 'Dự đoán rời bỏ',   href: '/super-admin/churn-prediction', icon: ShieldAlert, perm: 'superadmin.dashboard.view' },
        // Billing — superadmin.billing.manage
        { title: 'Billing Center',   href: '/super-admin/billing',     icon: BadgeDollarSign, perm: 'superadmin.billing.manage' },
        { title: 'Gói dịch vụ',     href: '/super-admin/plans',       icon: BadgeDollarSign, perm: 'superadmin.billing.manage' },
        { title: 'Mã giảm giá',      href: '/super-admin/coupons',     icon: Tag, perm: 'superadmin.billing.manage' },
        { title: 'Chiến dịch theo mùa', href: '/super-admin/campaign-templates', icon: Gift, perm: 'superadmin.billing.manage' },
        { title: 'Hoa hồng & Rút tiền', href: '/super-admin/referrals', icon: Crown, perm: 'superadmin.billing.manage' },
        // Support — superadmin.support.manage
        { title: 'DevOps & Support', href: '/super-admin/support',    icon: Headset, perm: 'superadmin.support.manage' },
        { title: 'Chatbot AI',        href: '/super-admin/chatbot',    icon: Bot, perm: 'superadmin.support.manage' },
        { title: 'Chatbot Diagnostics', href: '/super-admin/chatbot-diagnostics', icon: Brain, perm: 'superadmin.support.manage' },
        { title: 'Chiến dịch Quảng bá', href: '/super-admin/campaigns', icon: Megaphone, perm: 'superadmin.support.manage' },
        // System — superadmin.system.manage
        { title: 'Tài khoản',        href: '/super-admin/accounts',    icon: Users, perm: 'superadmin.system.manage' },
        { title: 'Audit Log',        href: '/super-admin/audit-logs',  icon: FileSearch2, perm: 'superadmin.system.manage' },
        { title: 'Quản lý Tường lửa', href: '/super-admin/firewall',   icon: ShieldCheck, perm: 'superadmin.system.manage' },
        { title: 'Cấu hình hệ thống', href: '/super-admin/settings', icon: Settings, perm: 'superadmin.system.manage' },
        { title: 'Giám sát Dịch vụ', href: '/super-admin/service-monitor', icon: Activity, perm: 'superadmin.system.manage' },
        { title: 'Lịch bảo trì hệ thống', href: '/super-admin/maintenance-schedules', icon: CalendarDays, perm: 'superadmin.system.manage' },
        { title: 'Sao lưu & Tối ưu DB', href: '/super-admin/backup-maintenance', icon: Database, perm: 'superadmin.system.manage' },
        { title: 'Dọn dẹp rác',      href: '/super-admin/garbage-collector', icon: Trash2, perm: 'superadmin.system.manage' },
        { title: 'Meilisearch Console', href: '/super-admin/meilisearch-console', icon: Database, perm: 'superadmin.system.manage' },
    ];

    return all.filter(item => !item.perm || canAdmin(item.perm));
});

// ─── OWNER MENU ───────────────────────────────────────────────────────────────
const ownerNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Tổng quan',        href: '/dashboard',              icon: LayoutGrid },
        { title: 'Quản lý đơn hàng', href: '/orders',                 icon: ShoppingCart },
        { title: 'Thực đơn & Món',   href: '/products',               icon: UtensilsCrossed },
        { title: 'Menu Engineering',  href: '/menu-engineering',       icon: BarChart3, feature: 'advanced_analytics' },
        { title: 'Kho nguyên liệu',  href: '/inventory',              icon: Package, feature: 'inventory_basic' },
        { title: 'Hao hụt & Lãng phí', href: '/waste-management',     icon: Trash2, feature: 'inventory_basic' },
        { title: 'BI Dashboard',        href: '/bi-dashboard',           icon: BarChart3, permission: 'view_report', feature: 'advanced_analytics' },
        { title: 'Phân tích địa lý',   href: '/geo-analytics',          icon: Route, feature: 'advanced_analytics' },
        { title: 'Mục tiêu & OKR',     href: '/business-goals',         icon: BarChart3, feature: 'advanced_analytics' },
        { title: 'Phân quyền thao tác', href: '/operation-policies',    icon: ShieldCheck },
        { title: 'Thiết bị & Bảo trì', href: '/equipment',             icon: Settings },
        { title: 'Đào tạo nhân viên', href: '/training',              icon: BookOpen, feature: 'hr_timekeeping' },
        { title: 'Checklist vận hành', href: '/operations-checklist', icon: ClipboardCheck },
        { title: 'Nhà cung cấp',     href: '/suppliers',              icon: Truck, feature: 'inventory_basic' },
        { title: 'Đấu thầu RFP',     href: '/rfps',                   icon: ScrollText, feature: 'supplier_portal' },
        { title: 'Giao hàng',        href: '/delivery',               icon: Route, feature: 'qr_ordering' },
        { title: 'Đặt hàng Online', href: '/online-store',            icon: Globe, feature: 'qr_ordering' },
        { title: 'Nhân sự',          href: '/employees',              icon: UserCheck, feature: 'hr_timekeeping' },
        { title: 'Chấm công & Lịch', href: '/schedules',              icon: CalendarDays, feature: 'hr_timekeeping' },
        { title: 'Bảng lương',       href: '/salaries',               icon: Wallet, permission: 'manage_salary', feature: 'hr_full' },
        { title: 'Phê duyệt',        href: '/approvals',              icon: ShieldCheck, badge: pendingApprovalCount.value },
        { title: 'Khách hàng',       href: '/customers',              icon: Users },
        { title: 'Khách hàng thân thiết', href: '/loyalty',           icon: Gift, feature: 'advanced_analytics' },
        { title: 'Khuyến mãi',       href: '/promotions',             icon: Tag, feature: 'advanced_analytics' },
        { title: 'Hóa đơn & Gói cước', href: '/billing/history',      icon: Receipt },
        { title: 'Cài đặt & Tích hợp', href: '/settings/integrations', icon: Settings },
        { title: 'Giới thiệu & Hoa hồng', href: '/settings/referrals', icon: Gift },
        { title: 'Phản hồi KH',      href: '/feedback',               icon: MessageSquare, permission: 'manage_feedback' },
        { title: 'Báo cáo & AI',     href: '/reports',                icon: BarChart3, permission: 'view_report', feature: 'advanced_analytics' },
        { title: 'Báo cáo Lãi/Lỗ',  href: '/reports/profit-loss',    icon: Wallet, permission: 'view_report' },
        { title: 'Trợ lý AI Chiến lược', href: '/ai-advisor',             icon: Bot, permission: 'view_report', feature: 'ai_advisor' },
        { title: 'Chốt ca',          href: '/shift-closings',         icon: ClipboardCheck, feature: 'inventory_basic' },
        { title: 'Quản lý dòng tiền', href: '/cash-flow',             icon: Wallet, feature: 'inventory_basic' },
        { title: 'Quản lý chi phí',  href: '/expenses',               icon: Receipt, feature: 'inventory_basic' },
        { title: 'Quản lý công nợ', href: '/debts',                  icon: BadgeDollarSign, feature: 'inventory_basic' },
        { title: 'Đánh giá & KPI',   href: '/kpis',                   icon: BarChart3, feature: 'hr_full' },
        { title: 'Kiểm toán Gian lận', href: '/fraud',                icon: ShieldAlert, feature: 'fraud_detection' },
        { title: 'Vi phạm nội bộ',   href: '/violations',             icon: FileSearch2, permission: 'view_violations', feature: 'fraud_detection' },
        { title: 'Sơ đồ bàn',        href: '/tables',                 icon: Building2 },
        { title: 'Audit Log',        href: '/audit-logs',             icon: ScrollText, permission: 'view_audit_log', feature: 'advanced_analytics' },
        { title: 'Tin tức',          href: '/tin-tuc',                icon: Newspaper },
        { title: 'Liên hệ & Hỗ trợ', href: '/support',                icon: Headset },
    ];

    return nav.filter(item => {
        if (isManager.value) {
            if (item.href === '/fraud' || item.href === '/audit-logs') {
                return false;
            }
        }

        if (item.permission && !can(item.permission)) {
            return false;
        }

        if (item.feature && !canFeature(item.feature as any)) {
            return false;
        }

        return true;
    });
});

// ─── MANAGER MENU ─────────────────────────────────────────────────────────────
const managerNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Tổng quan',        href: '/dashboard',              icon: LayoutGrid },
        { title: 'Đơn hàng hôm nay', href: '/orders',                 icon: ShoppingCart },
        { title: 'Kho nguyên liệu',  href: '/inventory',              icon: Package, feature: 'inventory_basic' },
        { title: 'Đấu thầu RFP',     href: '/rfps',                   icon: ScrollText, feature: 'supplier_portal' },
        { title: 'Giao hàng',        href: '/delivery',               icon: Route, feature: 'qr_ordering' },
        { title: 'Đặt hàng Online', href: '/online-store',            icon: Globe, feature: 'qr_ordering' },
        { title: 'Nhân sự',          href: '/employees',              icon: UserCheck, feature: 'hr_timekeeping' },
        { title: 'Chấm công & Lịch', href: '/schedules',              icon: CalendarDays, feature: 'hr_timekeeping' },
        { title: 'Bảng lương',       href: '/salaries',               icon: Wallet, permission: 'manage_salary', feature: 'hr_full' },
        { title: 'Khách hàng',       href: '/customers',              icon: Users },
        { title: 'Khách hàng thân thiết', href: '/loyalty',           icon: Gift, feature: 'advanced_analytics' },
        { title: 'Khuyến mãi',       href: '/promotions',             icon: Tag, feature: 'advanced_analytics' },
        { title: 'Hóa đơn & Gói cước', href: '/billing/history',      icon: Receipt },
        { title: 'Giới thiệu & Hoa hồng', href: '/settings/referrals', icon: Gift },
        { title: 'Phản hồi KH',      href: '/feedback',               icon: MessageSquare, permission: 'manage_feedback' },
        { title: 'Báo cáo doanh thu', href: '/reports',               icon: BarChart3, permission: 'view_report', feature: 'advanced_analytics' },
        { title: 'Trợ lý AI Chiến lược', href: '/ai-advisor',             icon: Bot, permission: 'view_report', feature: 'ai_advisor' },
        { title: 'Chốt ca',          href: '/shift-closings',         icon: ClipboardCheck, feature: 'inventory_basic' },
        { title: 'Quản lý dòng tiền', href: '/cash-flow',             icon: Wallet, feature: 'inventory_basic' },
        { title: 'Quản lý chi phí',  href: '/expenses',               icon: Receipt, feature: 'inventory_basic' },
        { title: 'Quản lý công nợ', href: '/debts',                  icon: BadgeDollarSign, feature: 'inventory_basic' },
        { title: 'Đánh giá & KPI',   href: '/kpis',                   icon: BarChart3, feature: 'hr_full' },
        { title: 'Kiểm toán Gian lận', href: '/fraud',                icon: ShieldAlert, feature: 'fraud_detection' },
        { title: 'Vi phạm nội bộ',   href: '/violations',             icon: FileSearch2, permission: 'view_violations', feature: 'fraud_detection' },
        { title: 'Tin tức',          href: '/tin-tuc',                icon: Newspaper },
        { title: 'Liên hệ & Hỗ trợ', href: '/support',                icon: Headset },
    ];

    return nav.filter(item => {
        if (item.permission && !can(item.permission)) {
            return false;
        }

        if (item.feature && !canFeature(item.feature as any)) {
            return false;
        }

        return true;
    });
});

// ─── CASHIER MENU ─────────────────────────────────────────────────────────────
const cashierNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Trang chủ',       href: '/dashboard',               icon: LayoutGrid },
        { title: 'Lịch sử đơn',     href: '/orders',                  icon: ScrollText },
        { title: 'Doanh thu ca',     href: '/shift-closings',          icon: ClipboardCheck, feature: 'inventory_basic' },
        { title: 'Dòng tiền',        href: '/cash-flow',               icon: Wallet, feature: 'inventory_basic' },
        { title: 'Lịch làm việc',   href: '/schedules',               icon: CalendarDays, feature: 'hr_timekeeping' },
        { title: 'Tố cáo ẩn danh',   href: '/violations',              icon: ShieldAlert },
    ];

    return nav.filter(item => !item.feature || canFeature(item.feature as any));
});

// ─── KITCHEN MENU ─────────────────────────────────────────────────────────────
const kitchenNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Trang chủ',       href: '/dashboard',               icon: LayoutGrid },
        { title: 'Lịch làm việc',   href: '/schedules',                icon: CalendarDays, feature: 'hr_timekeeping' },
        { title: 'Doanh thu ca',     href: '/shift-closings',          icon: ClipboardCheck, feature: 'inventory_basic' },
        { title: 'Lịch sử đơn',     href: '/orders',                  icon: ScrollText },
        { title: 'Tố cáo ẩn danh',   href: '/violations',              icon: ShieldAlert },
    ];

    return nav.filter(item => !item.feature || canFeature(item.feature as any));
});

// ─── INVENTORY STAFF MENU ─────────────────────────────────────────────────────
const inventoryNav = computed<NavItem[]>(() => {
    // Nhập kho & lịch sử giao dịch nằm sẵn dưới dạng tab trong trang Tồn kho,
    // không phải route riêng — không tạo mục menu trỏ tới URL không tồn tại.
    // 'Trang chủ' trỏ /dashboard, DashboardController sẽ tự redirect thẳng vào /inventory
    // (giống pattern Kitchen/Cashier), đồng thời giữ đúng vị trí item[0] để mainNavItems
    // chèn 'Cổng nhân sự' vào item[1] thay vì đẩy lên đầu danh sách.
    const nav = [
        { title: 'Trang chủ',       href: '/dashboard',                icon: LayoutGrid },
        { title: 'Tồn kho',         href: '/inventory',                icon: Package, feature: 'inventory_basic' },
        { title: 'Nhà cung cấp',    href: '/suppliers',                icon: Truck, feature: 'inventory_basic' },
        { title: 'Lịch làm việc',   href: '/schedules',                icon: CalendarDays, feature: 'hr_timekeeping' },
    ];

    return nav.filter(item => !item.feature || canFeature(item.feature as any));
});

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

const mainNavItems = computed<NavItem[]>(() => {
    let items: NavItem[] = [];

    if (isSuperAdmin.value) {
        items = superAdminNav.value;
    } else if (isSupplier.value) {
        items = supplierNav;
    } else if (isShipper.value) {
        items = shipperNav;
    } else if (!isSubscriptionActive.value) {
        items = [
            { title: 'Tổng quan', href: '/dashboard', icon: LayoutGrid }
        ];
    } else if (isOwner.value) {
        items = [...ownerNav.value];
    } else if (isManager.value) {
        items = [...managerNav.value];
    } else if (isCashier.value) {
        items = [...cashierNav.value];
    } else if (isKitchen.value) {
        items = [...kitchenNav.value];
    } else if (isInventory.value) {
        items = [...inventoryNav.value];
    }

    const showPortalLink = !isSuperAdmin.value && !isSupplier.value && !isOwner.value;

    if (showPortalLink && items.length > 0) {
        if (items[0] && (items[0].title === 'Tổng quan' || items[0].title === 'Trang chủ')) {
            const first = items[0];
            const rest = items.slice(1);
            items = [
                first,
                { title: 'Cổng nhân sự', href: '/employee-portal', icon: UserCheck },
                ...rest
            ];
        } else {
            items = [
                { title: 'Cổng nhân sự', href: '/employee-portal', icon: UserCheck },
                ...items
            ];
        }
    }

    return items;
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
