<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Bot,
    Brain,
    Building2,
    BadgeDollarSign,
    CheckSquare,
    ClipboardCheck,
    Image,
    Newspaper,
    Users,
    FileSearch2,
    Flame,
    Headset,
    UtensilsCrossed,
    Megaphone,
    ShoppingCart,
    Package,
    PackageCheck,
    Scale,
    Boxes,
    Calculator,
    Landmark,
    Receipt,
    BarChart3,
    Wallet,
    MessageSquare,
    BookOpen,
    Tag,
    Truck,
    Route,
    UserCheck,
    CalendarDays,
    ScrollText,
    ShieldCheck,
    ShieldAlert,
    Siren,
    RotateCcw,
    ArrowLeftRight,
    BookLock,
    SlidersHorizontal,
    Inbox,
    Handshake,
    Gift,
    Activity,
    Settings,
    Crown,
    Trash2,
    Database,
    ServerCog,
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
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { useFeatureGate } from '@/composables/useFeatureGate';
import { dashboard } from '@/routes';
import { dashboard as superAdminDashboard } from '@/routes/superadmin';
import type { NavItem } from '@/types';

const { can: canFeature } = useFeatureGate();

const page = usePage();

const tenant = computed(() => page.props.tenant as any);
const pendingApprovalCount = computed(
    () => (page.props.pendingApprovalCount as number) ?? 0,
);
const myOpenRequestCount = computed(
    () => (page.props.myOpenRequestCount as number) ?? 0,
);

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

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});

const hasRole = (...roleNames: string[]) =>
    roles.value.some((r: string) => roleNames.includes(r));

const isLegacySuperAdmin = computed(() => hasRole('super_admin'));
const isSuperAdmin = computed(() =>
    hasRole(
        'super_admin',
        'system_admin',
        'billing_admin',
        'support_specialist',
    ),
);
const isOwner = computed(() => hasRole('owner'));
const isManager = computed(() => hasRole('manager'));
const isCashier = computed(() => hasRole('cashier'));
const isWaiter = computed(() => hasRole('waiter'));
const isKitchen = computed(() => hasRole('kitchen'));
const isInventory = computed(() => hasRole('inventory_staff'));
const isOperationsInspector = computed(() =>
    hasRole('operations_inspector', 'compliance_auditor'),
);
const isWarehouseManager = computed(() => hasRole('warehouse_manager'));
const isWarehouseStaff = computed(() => hasRole('warehouse_staff'));
const usesManagementSidebar = computed(
    () =>
        isSuperAdmin.value ||
        isOwner.value ||
        isManager.value ||
        isWarehouseManager.value ||
        isOperationsInspector.value ||
        isWarehouseStaff.value,
);
const isSupplier = computed(() => hasRole('supplier'));
const supplierPortalEnabled = computed(() =>
    Boolean((page.props as any).supplier_portal_enabled),
);
const isShipper = computed(() => hasRole('shipper'));

// Lấy danh sách permissions từ Inertia shared state
const permissions = computed(() => {
    const authUser = page.props.auth?.user as any;

    return authUser?.permissions ?? [];
});

// Kiểm tra quyền Spatie
const can = (permission: string) => {
    if (isLegacySuperAdmin.value) {
        return true;
    }

    return permissions.value.includes(permission);
};

// ─── SUPER ADMIN MENU ────────────────────────────────────────────────────────
const canAdmin = (perm: string) =>
    isLegacySuperAdmin.value || permissions.value.includes(perm);

// ─── OWNER MENU ───────────────────────────────────────────────────────────────
const superAdminNav = computed<NavItem[]>(() => {
    const all: (NavItem & { perm?: string })[] = [
        {
            title: 'Tổng quan',
            href: superAdminDashboard().url,
            icon: LayoutGrid,
            section: 'superadmin_overview',
            perm: 'superadmin.dashboard.view',
            prefetch: true,
        },
        {
            title: 'Sức khỏe nhà hàng & chi nhánh',
            href: '/super-admin/tenant-health',
            icon: Activity,
            section: 'superadmin_overview',
            perm: 'superadmin.tenant.view',
        },
        {
            title: 'Nhà hàng',
            href: '/super-admin/restaurants',
            icon: Building2,
            section: 'superadmin_tenants',
            perm: 'superadmin.tenant.view',
        },
        {
            title: 'Đơn hàng hệ thống',
            href: '/super-admin/orders',
            icon: ShoppingCart,
            section: 'superadmin_tenants',
            perm: 'superadmin.tenant.view',
        },
        {
            title: 'Doanh thu hệ thống',
            href: '/super-admin/revenue',
            icon: Wallet,
            section: 'superadmin_overview',
            perm: 'superadmin.tenant.view',
        },
        {
            title: 'Khách hàng',
            href: '/super-admin/customers',
            icon: Users,
            section: 'superadmin_growth',
            perm: 'superadmin.tenant.view',
        },
        {
            title: 'Phản hồi',
            href: '/super-admin/feedback',
            icon: MessageSquare,
            section: 'superadmin_growth',
            perm: 'superadmin.tenant.view',
        },
        {
            title: 'Dự đoán rời bỏ',
            href: '/super-admin/churn-prediction',
            icon: ShieldAlert,
            section: 'superadmin_overview',
            perm: 'superadmin.tenant.view',
        },
        {
            title: 'Banner & trình chiếu',
            href: '/super-admin/banners',
            icon: Image,
            section: 'superadmin_growth',
            perm: 'superadmin.content.manage',
        },
        {
            title: 'Tin tức',
            href: '/super-admin/news',
            icon: Newspaper,
            section: 'superadmin_growth',
            perm: 'superadmin.content.manage',
        },
        {
            title: 'Trung tâm thanh toán',
            href: '/super-admin/billing',
            icon: BadgeDollarSign,
            section: 'superadmin_finance',
            perm: 'superadmin.billing.view',
        },
        {
            title: 'Gói dịch vụ',
            href: '/super-admin/plans',
            icon: BadgeDollarSign,
            section: 'superadmin_finance',
            perm: 'superadmin.billing.view',
        },
        {
            title: 'Mã giảm giá',
            href: '/super-admin/coupons',
            icon: Tag,
            section: 'superadmin_finance',
            perm: 'superadmin.billing.view',
        },
        {
            title: 'Chiến dịch thanh toán',
            href: '/super-admin/campaign-templates',
            icon: Gift,
            section: 'superadmin_finance',
            perm: 'superadmin.billing.view',
        },
        {
            title: 'Hoa hồng & Rút tiền',
            href: '/super-admin/referrals',
            icon: Crown,
            section: 'superadmin_finance',
            perm: 'superadmin.billing.view',
        },
        {
            title: 'Hỗ trợ kỹ thuật',
            href: '/super-admin/support',
            icon: Headset,
            section: 'superadmin_support',
            perm: 'superadmin.support.manage',
        },
        {
            title: 'Trợ lý AI',
            href: '/super-admin/chatbot',
            icon: Bot,
            section: 'superadmin_support',
            perm: 'superadmin.support.manage',
        },
        {
            title: 'Chẩn đoán trợ lý AI',
            href: '/super-admin/chatbot-diagnostics',
            icon: Brain,
            section: 'superadmin_support',
            perm: 'superadmin.support.manage',
        },
        {
            title: 'Chiến dịch thông báo',
            href: '/super-admin/campaigns',
            icon: Megaphone,
            section: 'superadmin_growth',
            perm: 'superadmin.support.manage',
        },
        {
            title: 'Tài khoản nền tảng',
            href: '/super-admin/accounts',
            icon: Users,
            section: 'superadmin_security',
            perm: 'superadmin.system.manage',
        },
        {
            title: 'Nhật ký hệ thống',
            href: '/super-admin/audit-logs',
            icon: FileSearch2,
            section: 'superadmin_security',
            perm: 'superadmin.audit.view',
        },
        {
            title: 'Quản lý Tường lửa',
            href: '/super-admin/firewall',
            icon: ShieldCheck,
            section: 'superadmin_security',
            perm: 'superadmin.security.manage',
        },
        {
            title: 'Cấu hình hệ thống',
            href: '/super-admin/settings',
            icon: Settings,
            section: 'superadmin_operations',
            perm: 'superadmin.system.manage',
        },
        {
            title: 'Giám sát Dịch vụ',
            href: '/super-admin/service-monitor',
            icon: Activity,
            section: 'superadmin_operations',
            perm: 'superadmin.system.manage',
        },
        {
            title: 'Trung tâm vận hành',
            href: '/super-admin/operations',
            icon: ServerCog,
            section: 'superadmin_operations',
            perm: 'superadmin.system.manage',
        },
        {
            title: 'Lịch bảo trì hệ thống',
            href: '/super-admin/maintenance-schedules',
            icon: CalendarDays,
            section: 'superadmin_operations',
            perm: 'superadmin.system.manage',
        },
        {
            title: 'Sao lưu & tối ưu cơ sở dữ liệu',
            href: '/super-admin/backup-maintenance',
            icon: Database,
            section: 'superadmin_operations',
            perm: 'superadmin.backup.manage',
        },
        {
            title: 'Vòng đời dữ liệu & dung lượng',
            href: '/super-admin/data-lifecycle',
            icon: Database,
            section: 'superadmin_operations',
            perm: 'superadmin.backup.manage',
        },
        {
            title: 'Dọn dẹp rác',
            href: '/super-admin/garbage-collector',
            icon: Trash2,
            section: 'superadmin_operations',
            perm: 'superadmin.system.manage',
        },
        {
            title: 'Bảng điều khiển tìm kiếm',
            href: '/super-admin/meilisearch-console',
            icon: Database,
            section: 'superadmin_operations',
            perm: 'superadmin.system.manage',
        },
        {
            title: 'Giám sát tài nguyên',
            href: '/super-admin/resource-limits',
            icon: Activity,
            section: 'superadmin_operations',
            perm: 'superadmin.system.manage',
        },
        {
            title: 'Trung tâm bảo mật',
            href: '/super-admin/security-center',
            icon: ShieldCheck,
            section: 'superadmin_security',
            perm: 'superadmin.security.manage',
        },
        {
            title: 'Lịch demo tư vấn',
            href: '/super-admin/demo-bookings',
            icon: CalendarDays,
            section: 'superadmin_growth',
            perm: 'superadmin.system.manage',
        },
    ];

    return all.filter((item) => !item.perm || canAdmin(item.perm));
});

const ownerNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Tổng quan', href: '/dashboard', icon: LayoutGrid },
        {
            title: 'Trung tâm điều hành chuỗi',
            href: '/enterprise/command-center',
            icon: Activity,
            feature: 'advanced_analytics',
        },
        {
            title: 'Trung tâm Chứng từ & Phiếu',
            href: '/enterprise/documents',
            icon: ScrollText,
        },
        { title: 'Quản lý đơn hàng', href: '/orders', icon: ShoppingCart },
        { title: 'Đặt bàn', href: '/reservations', icon: CalendarDays },
        { title: 'Thực đơn & Món', href: '/products', icon: UtensilsCrossed },
        {
            title: 'Phân tích thực đơn',
            href: '/menu-engineering',
            icon: BarChart3,
            feature: 'advanced_analytics',
        },
        {
            title: 'Món bán chạy',
            href: '/best-sellers',
            icon: Flame,
            feature: 'advanced_analytics',
        },
        {
            title: 'Kho nguyên liệu',
            href: '/inventory',
            icon: Package,
            feature: 'inventory_basic',
        },
        {
            title: 'Công thức định lượng',
            href: '/inventory/recipes',
            icon: Scale,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Điều chuyển kho',
            href: '/inventory/transfers',
            icon: ArrowLeftRight,
            feature: 'inventory_basic',
        },
        {
            title: 'Hao hụt & Lãng phí',
            href: '/waste-management',
            icon: Trash2,
            feature: 'inventory_basic',
        },
        {
            title: 'Bảng điều khiển BI',
            href: '/bi-dashboard',
            icon: BarChart3,
            permission: 'view_report',
            feature: 'advanced_analytics',
        },
        {
            title: 'Mục tiêu & OKR',
            href: '/business-goals',
            icon: BarChart3,
            feature: 'advanced_analytics',
        },
        {
            title: 'Phân quyền thao tác',
            href: '/operation-policies',
            icon: ShieldCheck,
        },
        { title: 'Thiết bị & Bảo trì', href: '/equipment', icon: Settings },
        {
            title: 'Đào tạo nhân viên',
            href: '/training',
            icon: BookOpen,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Checklist vận hành',
            href: '/operations-checklist',
            icon: ClipboardCheck,
        },
        {
            title: 'Phê duyệt',
            href: '/approvals',
            icon: ShieldCheck,
            badge: pendingApprovalCount.value,
        },
        {
            title: 'Quản lý đã duyệt gì',
            href: '/approvals/ledger',
            icon: BookLock,
        },
        {
            title: 'Thẩm quyền phê duyệt',
            href: '/approvals/policies',
            icon: SlidersHorizontal,
        },
        {
            title: 'Bộ Quy định & Tiêu chuẩn',
            href: '/operations/company-policies',
            icon: BookOpen,
        },
        {
            title: 'Thanh tra & Biên bản Phạt',
            href: '/operations/audit',
            icon: ShieldAlert,
        },
        {
            title: 'Tổng quan Kho Tổng',
            href: '/inventory/central-warehouse',
            icon: LayoutGrid,
            feature: 'inventory_basic',
        },
        {
            title: 'Xử lý âm nguyên liệu',
            href: '/inventory/negative-stock',
            icon: ShieldAlert,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Trợ lý AI Kho Tổng',
            href: '/inventory/central-warehouse/ai-advisor',
            icon: Bot,
            feature: 'inventory_basic',
        },
        {
            title: 'Đơn cấp phát',
            href: '/inventory/central-warehouse/requests',
            icon: ClipboardCheck,
            permission: 'supply_requests.view',
            feature: 'inventory_basic',
        },
        {
            title: 'Nhận hàng & GRN Kho Tổng',
            href: '/inventory/central-warehouse/receiving',
            icon: PackageCheck,
            feature: 'inventory_basic',
        },
        {
            title: 'Bảng giá nguyên liệu',
            href: '/inventory/central-warehouse/prices',
            icon: BadgeDollarSign,
            permission: 'warehouse.manage',
            feature: 'inventory_basic',
        },
        {
            title: 'Kiểm kê & Điều chỉnh',
            href: '/inventory/count-sessions',
            icon: ClipboardCheck,
            feature: 'inventory_basic',
        },
        {
            title: 'Chốt kho chi nhánh',
            href: '/inventory/branch-closing',
            icon: ClipboardCheck,
            permission: 'inventory.count',
            feature: 'inventory_basic',
        },
        {
            title: 'Chốt nguyên liệu',
            href: '/inventory/central-warehouse/material-closing',
            icon: ClipboardCheck,
            permission: 'inventory.count',
            feature: 'inventory_basic',
        },
        {
            title: 'Central Kitchen Sơ chế',
            href: '/inventory/central-kitchen',
            icon: Boxes,
            feature: 'inventory_basic',
        },
        {
            title: 'Chuyến xe Logistics',
            href: '/inventory/delivery-manifests',
            icon: Route,
            feature: 'inventory_basic',
        },
        {
            title: 'Thu hồi Lô Khẩn cấp',
            href: '/inventory/batch-recalls',
            icon: Siren,
            feature: 'inventory_basic',
        },
        {
            title: 'Cách ly & Hoàn trả',
            href: '/inventory/reverse-logistics',
            icon: RotateCcw,
            feature: 'inventory_basic',
        },
        {
            title: 'Quản trị Siết chặt Kho',
            href: '/inventory/warehouse-governance',
            icon: ShieldAlert,
            feature: 'inventory_basic',
        },
        {
            title: 'Nhà cung cấp',
            href: '/suppliers',
            icon: Truck,
            feature: 'inventory_basic',
        },
        {
            title: 'Đấu thầu RFP',
            href: '/rfps',
            icon: ScrollText,
            feature: 'supplier_portal',
        },
        {
            title: 'Nhân sự',
            href: '/employees',
            icon: UserCheck,
            feature: 'hr_timekeeping',
            section: 'people',
        },
        {
            title: 'Điều phối & Đội ngũ Kho',
            href: '/warehouse/team',
            icon: Users,
            permission: 'warehouse.staff.view',
            feature: 'inventory_basic',
            section: 'people',
        },
        {
            title: 'Chấm công & Lịch',
            href: '/schedules',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
            section: 'people',
        },
        {
            title: 'Bảng lương',
            href: '/salaries',
            icon: Wallet,
            permission: 'manage_salary',
            feature: 'hr_full',
        },
        {
            title: 'Quỹ lương & Ngân sách',
            href: '/payroll-budget',
            icon: Wallet,
            feature: 'hr_full',
        },
        {
            title: 'Thưởng nhân viên',
            href: '/bonuses',
            icon: Gift,
            feature: 'hr_full',
        },
        {
            title: 'Tăng ca',
            href: '/overtime-requests',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        { title: 'Khách hàng', href: '/customers', icon: Users },
        {
            title: 'Khách hàng thân thiết',
            href: '/loyalty',
            icon: Gift,
            feature: 'advanced_analytics',
        },
        {
            title: 'Khuyến mãi',
            href: '/promotions',
            icon: Tag,
            feature: 'advanced_analytics',
        },
        {
            title: 'Hiệu quả khuyến mãi',
            href: '/promotions/analytics',
            icon: Tag,
            feature: 'advanced_analytics',
        },
        {
            title: 'Trigger khuyến mãi',
            href: '/promotions/triggers',
            icon: Tag,
            feature: 'advanced_analytics',
        },
        {
            title: 'Hóa đơn & Gói cước',
            href: '/billing/history',
            icon: Receipt,
        },
        {
            title: 'Cài đặt & Tích hợp',
            href: '/settings/integrations',
            icon: Settings,
        },
        {
            title: 'Quản trị chi nhánh',
            href: '/settings/branches',
            icon: Building2,
        },
        {
            title: 'Thông tin nhà hàng',
            href: '/settings/restaurant',
            icon: Building2,
        },
        {
            title: 'Giới thiệu & Hoa hồng',
            href: '/settings/referrals',
            icon: Gift,
        },
        {
            title: 'Phản hồi KH',
            href: '/feedback',
            icon: MessageSquare,
            permission: 'manage_feedback',
        },
        {
            title: 'Báo cáo & AI',
            href: '/reports',
            icon: BarChart3,
            permission: 'view_report',
            feature: 'advanced_analytics',
        },
        {
            title: 'Trợ lý AI Chiến lược',
            href: '/ai-advisor',
            icon: Bot,
            permission: 'view_report',
            feature: 'ai_advisor',
        },
        {
            title: 'Chốt ca',
            href: '/shift-closings',
            icon: ClipboardCheck,
            feature: 'inventory_basic',
        },
        {
            title: 'Bàn giao ca',
            href: '/shift-handovers',
            icon: Handshake,
            feature: 'inventory_basic',
        },
        {
            title: 'Quản lý dòng tiền',
            href: '/cash-flow',
            icon: Wallet,
            feature: 'inventory_basic',
        },
        {
            title: 'Quản lý chi phí',
            href: '/expenses',
            icon: Receipt,
            feature: 'inventory_basic',
        },
        {
            title: 'Công nợ',
            href: '/debts',
            icon: BadgeDollarSign,
            feature: 'inventory_basic',
        },
        {
            title: 'Sổ tài chính',
            href: '/finance',
            icon: BookOpen,
            permission: 'finance.view',
        },
        {
            title: 'Giá trị nhập nguyên liệu',
            href: '/finance/ingredient-spend',
            icon: BadgeDollarSign,
            permission: 'finance.view',
        },
        {
            title: 'Ngân sách tài chính',
            href: '/financial-budgets',
            icon: Calculator,
            permission: 'finance.manage',
        },
        {
            title: 'Đối soát ngân hàng',
            href: '/bank-reconciliation',
            icon: Landmark,
            permission: 'finance.manage',
        },
        {
            title: 'Tài sản cố định',
            href: '/fixed-assets',
            icon: Boxes,
            permission: 'finance.manage',
        },
        {
            title: 'Đánh giá & KPI',
            href: '/kpis',
            icon: BarChart3,
            feature: 'hr_full',
        },
        {
            title: 'Kiểm toán Gian lận',
            href: '/fraud',
            icon: ShieldAlert,
            permission: 'view_fraud_detection',
            feature: 'fraud_detection',
        },
        {
            title: 'Vi phạm nội bộ',
            href: '/violations',
            icon: FileSearch2,
            permission: 'view_violations',
            feature: 'fraud_detection',
        },
        { title: 'Sự cố khẩn cấp', href: '/incidents', icon: Siren },
        { title: 'Sơ đồ bàn', href: '/tables', icon: Building2 },
        {
            title: 'Nhật ký hệ thống',
            href: '/audit-logs',
            icon: ScrollText,
            permission: 'view_audit_log',
            feature: 'advanced_analytics',
        },
        { title: 'Tin tức', href: '/tin-tuc', icon: Newspaper },
        { title: 'Liên hệ & Hỗ trợ', href: '/support', icon: Headset },
    ];

    const sectionByHref: Record<string, string> = {
        '/dashboard': 'overview',
        '/enterprise/command-center': 'overview',
        '/bi-dashboard': 'overview',
        '/geo-analytics': 'overview',
        '/business-goals': 'overview',
        '/reports': 'overview',
        '/ai-advisor': 'overview',
        '/orders': 'sales',
        '/reservations': 'sales',
        '/tables': 'sales',
        '/delivery': 'sales',
        '/online-store': 'sales',
        '/products': 'menu',
        '/menu-engineering': 'menu',
        '/best-sellers': 'menu',
        '/inventory': 'supply',
        '/inventory/recipes': 'supply',
        '/inventory/transfers': 'supply',
        '/waste-management': 'supply',
        '/inventory/central-warehouse': 'supply',
        '/inventory/central-warehouse/requests': 'supply',
        '/inventory/central-warehouse/receiving': 'supply',
        '/inventory/central-warehouse/prices': 'supply',
        '/inventory/central-warehouse/ai-advisor': 'supply',
        '/inventory/central-warehouse/stock': 'supply',
        '/inventory/count-sessions': 'supply',
        '/inventory/central-warehouse/material-closing': 'supply',
        '/inventory/central-kitchen': 'supply',
        '/inventory/delivery-manifests': 'supply',
        '/inventory/batch-recalls': 'supply',
        '/inventory/reverse-logistics': 'supply',
        '/inventory/warehouse-governance': 'supply',
        '/suppliers': 'supply',
        '/rfps': 'supply',
        '/shift-closings': 'finance',
        '/cash-flow': 'finance',
        '/expenses': 'finance',
        '/debts': 'finance',
        '/finance': 'finance',
        '/finance/ingredient-spend': 'finance',
        '/financial-budgets': 'finance',
        '/bank-reconciliation': 'finance',
        '/fixed-assets': 'finance',
        '/billing/history': 'finance',
        '/employees': 'people',
        '/bonuses': 'people',
        '/overtime-requests': 'people',
        '/schedules': 'people',
        '/salaries': 'people',
        '/payroll-budget': 'people',
        '/training': 'people',
        '/kpis': 'people',
        '/customers': 'customers',
        '/loyalty': 'customers',
        '/promotions': 'customers',
        '/promotions/analytics': 'customers',
        '/promotions/triggers': 'customers',
        '/feedback': 'customers',
        '/equipment': 'operations',
        '/operations-checklist': 'operations',
        '/operations/company-policies': 'operations',
        '/shift-handovers': 'operations',
        '/incidents': 'operations',
        '/operation-policies': 'governance',
        '/approvals': 'governance',
        '/approvals/ledger': 'governance',
        '/approvals/policies': 'governance',
        '/operations/audit': 'governance',
        '/fraud': 'governance',
        '/violations': 'governance',
        '/audit-logs': 'governance',
        '/settings/branches': 'settings',
        '/settings/restaurant': 'settings',
        '/settings/integrations': 'settings',
        '/settings/referrals': 'settings',
        '/tin-tuc': 'settings',
        '/support': 'settings',
    };

    return nav
        .filter((item) => {
            if (isManager.value) {
                if (item.href === '/fraud' || item.href === '/audit-logs') {
                    return false;
                }
            }

            if (
                (item.href === '/suppliers' || item.href === '/rfps') &&
                !supplierPortalEnabled.value
            ) {
                return false;
            }

            if (item.permission && !can(item.permission)) {
                return false;
            }

            if (item.feature && !canFeature(item.feature as any)) {
                return false;
            }

            return true;
        })
        .map((item) => ({
            ...item,
            section: sectionByHref[String(item.href)],
        }));
});

// ─── MANAGER MENU ─────────────────────────────────────────────────────────────
const managerNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Tổng quan', href: '/dashboard', icon: LayoutGrid },
        { title: 'Quản lý đơn hàng', href: '/orders', icon: ShoppingCart },
        { title: 'Đặt bàn', href: '/reservations', icon: CalendarDays },
        { title: 'Thực đơn & Món', href: '/products', icon: UtensilsCrossed },
        {
            title: 'Phân tích thực đơn',
            href: '/menu-engineering',
            icon: BarChart3,
            feature: 'advanced_analytics',
        },
        {
            title: 'Món bán chạy',
            href: '/best-sellers',
            icon: Flame,
            feature: 'advanced_analytics',
        },
        {
            title: 'Kho nguyên liệu',
            href: '/inventory',
            icon: Package,
            feature: 'inventory_basic',
        },
        {
            title: 'Công thức định lượng',
            href: '/inventory/recipes',
            icon: Scale,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Nhận hàng Kho Tổng',
            href: '/inventory/branch-requisition',
            icon: PackageCheck,
            permission: 'supply_requests.receive',
            feature: 'inventory_basic',
        },
        {
            title: 'Xử lý âm nguyên liệu',
            href: '/inventory/negative-stock',
            icon: ShieldAlert,
            feature: 'inventory_basic',
        },
        {
            title: 'Chốt kho chi nhánh',
            href: '/inventory/branch-closing',
            icon: ClipboardCheck,
            permission: 'inventory.count',
            feature: 'inventory_basic',
        },
        {
            title: 'Điều chuyển kho',
            href: '/inventory/transfers',
            icon: ArrowLeftRight,
            feature: 'inventory_basic',
        },
        {
            title: 'Đấu thầu RFP',
            href: '/rfps',
            icon: ScrollText,
            feature: 'supplier_portal',
        },
        {
            title: 'Nhân sự',
            href: '/employees',
            icon: UserCheck,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Chấm công & Lịch',
            href: '/schedules',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Bảng lương',
            href: '/salaries',
            icon: Wallet,
            permission: 'manage_salary',
            feature: 'hr_full',
        },
        {
            title: 'Thưởng nhân viên',
            href: '/bonuses',
            icon: Gift,
            feature: 'hr_full',
        },
        {
            title: 'Tăng ca',
            href: '/overtime-requests',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        { title: 'Khách hàng', href: '/customers', icon: Users },
        {
            title: 'Khách hàng thân thiết',
            href: '/loyalty',
            icon: Gift,
            feature: 'advanced_analytics',
        },
        {
            title: 'Khuyến mãi',
            href: '/promotions',
            icon: Tag,
            feature: 'advanced_analytics',
        },
        {
            title: 'Hiệu quả khuyến mãi',
            href: '/promotions/analytics',
            icon: Tag,
            feature: 'advanced_analytics',
        },
        {
            title: 'Trigger khuyến mãi',
            href: '/promotions/triggers',
            icon: Tag,
            feature: 'advanced_analytics',
        },
        {
            title: 'Hóa đơn & Gói cước',
            href: '/billing/history',
            icon: Receipt,
        },
        {
            title: 'Giới thiệu & Hoa hồng',
            href: '/settings/referrals',
            icon: Gift,
        },
        {
            title: 'Phản hồi KH',
            href: '/feedback',
            icon: MessageSquare,
            permission: 'manage_feedback',
        },
        {
            title: 'Báo cáo doanh thu',
            href: '/reports',
            icon: BarChart3,
            permission: 'view_report',
            feature: 'advanced_analytics',
        },
        {
            title: 'Món bán chạy',
            href: '/best-sellers',
            icon: Flame,
            permission: 'view_report',
            feature: 'advanced_analytics',
        },
        {
            title: 'Trợ lý AI Chiến lược',
            href: '/ai-advisor',
            icon: Bot,
            permission: 'view_report',
            feature: 'ai_advisor',
        },
        {
            title: 'Chốt ca',
            href: '/shift-closings',
            icon: ClipboardCheck,
            feature: 'inventory_basic',
        },
        {
            title: 'Bàn giao ca',
            href: '/shift-handovers',
            icon: Handshake,
            feature: 'inventory_basic',
        },
        {
            title: 'Quản lý dòng tiền',
            href: '/cash-flow',
            icon: Wallet,
            feature: 'inventory_basic',
        },
        {
            title: 'Quản lý chi phí',
            href: '/expenses',
            icon: Receipt,
            feature: 'inventory_basic',
        },
        {
            title: 'Sổ tài chính',
            href: '/finance',
            icon: BookOpen,
            permission: 'finance.view',
        },
        {
            title: 'Bàn giao tài sản',
            href: '/fixed-assets',
            icon: Boxes,
            section: 'finance',
        },
        {
            title: 'Đánh giá & KPI',
            href: '/kpis',
            icon: BarChart3,
            feature: 'hr_full',
        },
        {
            title: 'Kiểm toán Gian lận',
            href: '/fraud',
            icon: ShieldAlert,
            permission: 'view_fraud_detection',
            feature: 'fraud_detection',
        },
        {
            title: 'Vi phạm nội bộ',
            href: '/violations',
            icon: FileSearch2,
            permission: 'view_violations',
            feature: 'fraud_detection',
        },
        { title: 'Sự cố khẩn cấp', href: '/incidents', icon: Siren },
        {
            title: 'Phê duyệt',
            href: '/approvals',
            icon: ShieldCheck,
            badge: pendingApprovalCount.value,
        },
        {
            title: 'Yêu cầu của tôi',
            href: '/my-requests',
            icon: Inbox,
            badge: myOpenRequestCount.value,
        },
        { title: 'Tin tức', href: '/tin-tuc', icon: Newspaper },
        { title: 'Liên hệ & Hỗ trợ', href: '/support', icon: Headset },
    ];

    return nav.filter((item) => {
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
        { title: 'Trang chủ', href: '/dashboard', icon: LayoutGrid },
        { title: 'Cổng nhân sự', href: '/employee-portal', icon: UserCheck },
        { title: 'Lịch sử đơn', href: '/orders', icon: ScrollText },
        {
            title: 'Két tiền ca',
            href: '/cash-flow',
            icon: Wallet,
            feature: 'inventory_basic',
        },
        {
            title: 'Bàn giao ca',
            href: '/shift-handovers',
            icon: Handshake,
            feature: 'inventory_basic',
        },
        {
            title: 'Lịch làm việc',
            href: '/schedules',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Tăng ca',
            href: '/overtime-requests',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        { title: 'Tố cáo ẩn danh', href: '/violations', icon: ShieldAlert },
        { title: 'Sự cố khẩn cấp', href: '/incidents', icon: Siren },
        {
            title: 'Yêu cầu của tôi',
            href: '/my-requests',
            icon: Inbox,
            badge: myOpenRequestCount.value,
        },
    ];

    return nav.filter(
        (item) => !item.feature || canFeature(item.feature as any),
    );
});

// ─── WAITER (ORDER STAFF) MENU ────────────────────────────────────────────────
const waiterNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Trang chủ', href: '/dashboard', icon: LayoutGrid },
        { title: 'Cổng nhân sự', href: '/employee-portal', icon: UserCheck },
        {
            title: 'Bàn giao ca',
            href: '/shift-handovers',
            icon: Handshake,
            feature: 'inventory_basic',
        },
        {
            title: 'Lịch làm việc',
            href: '/schedules',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Tăng ca',
            href: '/overtime-requests',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        { title: 'Tố cáo ẩn danh', href: '/violations', icon: ShieldAlert },
        { title: 'Sự cố khẩn cấp', href: '/incidents', icon: Siren },
        {
            title: 'Yêu cầu của tôi',
            href: '/my-requests',
            icon: Inbox,
            badge: myOpenRequestCount.value,
        },
    ];

    return nav.filter(
        (item) => !item.feature || canFeature(item.feature as any),
    );
});

// ─── KITCHEN MENU ─────────────────────────────────────────────────────────────
const kitchenNav = computed<NavItem[]>(() => {
    const nav = [
        { title: 'Trang chủ', href: '/dashboard', icon: LayoutGrid },
        {
            title: 'Quản lý món',
            href: '/kitchen/menu-control',
            icon: UtensilsCrossed,
        },
        {
            title: 'Lịch làm việc',
            href: '/schedules',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Tăng ca',
            href: '/overtime-requests',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Bàn giao ca',
            href: '/shift-handovers',
            icon: Handshake,
            feature: 'inventory_basic',
        },
        { title: 'Lịch sử đơn', href: '/orders', icon: ScrollText },
        { title: 'Tố cáo ẩn danh', href: '/violations', icon: ShieldAlert },
        { title: 'Sự cố khẩn cấp', href: '/incidents', icon: Siren },
        {
            title: 'Yêu cầu của tôi',
            href: '/my-requests',
            icon: Inbox,
            badge: myOpenRequestCount.value,
        },
    ];

    return nav.filter(
        (item) => !item.feature || canFeature(item.feature as any),
    );
});

// ─── INVENTORY STAFF MENU ─────────────────────────────────────────────────────
const inventoryNav = computed<NavItem[]>(() => {
    // Nhập kho & lịch sử giao dịch vẫn nằm dưới dạng tab trong trang Tồn kho;
    // Công thức định lượng đã có route riêng để không trộn với danh sách tồn.
    // 'Trang chủ' trỏ /dashboard, DashboardController sẽ tự redirect thẳng vào /inventory
    // (giống pattern Kitchen/Cashier), đồng thời giữ đúng vị trí item[0] để mainNavItems
    // chèn 'Cổng nhân sự' vào item[1] thay vì đẩy lên đầu danh sách.
    const nav = [
        { title: 'Trang chủ', href: '/dashboard', icon: LayoutGrid },
        {
            title: 'Tồn kho',
            href: '/inventory',
            icon: Package,
            feature: 'inventory_basic',
        },
        {
            title: 'Công thức định lượng',
            href: '/inventory/recipes',
            icon: Scale,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Nhận hàng Kho Tổng',
            href: '/inventory/branch-requisition',
            icon: PackageCheck,
            permission: 'supply_requests.receive',
            feature: 'inventory_basic',
        },
        {
            title: 'Xử lý âm nguyên liệu',
            href: '/inventory/negative-stock',
            icon: ShieldAlert,
            feature: 'inventory_basic',
        },
        {
            title: 'Nhà cung cấp',
            href: '/suppliers',
            icon: Truck,
            feature: 'inventory_basic',
        },
        {
            title: 'Lịch làm việc',
            href: '/schedules',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Yêu cầu của tôi',
            href: '/my-requests',
            icon: Inbox,
            badge: myOpenRequestCount.value,
        },
    ];

    return nav.filter(
        (item) => !item.feature || canFeature(item.feature as any),
    );
});

// ─── OPERATIONS INSPECTOR / COMPLIANCE MENU ──────────────────────────────────
const operationsInspectorNav: NavItem[] = [
    {
        title: 'Tổng quan thanh tra',
        href: '/operations/audit/overview',
        icon: LayoutGrid,
        section: 'overview',
    },
    {
        title: 'Kế hoạch & Biên bản thanh tra',
        href: '/operations/audit',
        icon: ShieldAlert,
        section: 'governance',
    },
    {
        title: 'Phiên kiểm tra hiện trường',
        href: '/operations/inspection-workspace',
        icon: ClipboardCheck,
        section: 'operations',
    },
    {
        title: 'Bộ Quy định & Tiêu chuẩn',
        href: '/operations/company-policies',
        icon: BookOpen,
        section: 'operations',
    },
    {
        title: 'Checklist vận hành',
        href: '/operations-checklist',
        icon: CheckSquare,
        section: 'operations',
    },
    {
        title: 'Kiểm tra tài sản',
        href: '/fixed-assets',
        icon: Boxes,
        section: 'finance',
    },
    {
        title: 'Sự cố vận hành',
        href: '/incidents',
        icon: Siren,
        section: 'operations',
    },
    {
        title: 'Vi phạm nội bộ',
        href: '/violations',
        icon: FileSearch2,
        section: 'governance',
    },
    {
        title: 'Nhật ký kiểm toán',
        href: '/audit-logs',
        icon: ScrollText,
        section: 'governance',
    },
    {
        title: 'Liên hệ & Hỗ trợ',
        href: '/support',
        icon: Headset,
        section: 'settings',
    },
];

const warehouseManagerNav = computed<NavItem[]>(() => {
    const nav = [
        {
            title: 'Tổng quan Kho Tổng',
            href: '/inventory/central-warehouse',
            icon: LayoutGrid,
            feature: 'inventory_basic',
        },
        {
            title: 'Xử lý âm nguyên liệu',
            href: '/inventory/negative-stock',
            icon: ShieldAlert,
            feature: 'inventory_basic',
        },
        {
            title: 'Trợ lý AI Kho Tổng',
            href: '/inventory/central-warehouse/ai-advisor',
            icon: Bot,
            feature: 'inventory_basic',
        },
        {
            title: 'Đơn cấp phát',
            href: '/inventory/central-warehouse/requests',
            icon: ClipboardCheck,
            permission: 'supply_requests.view',
            feature: 'inventory_basic',
        },
        {
            title: 'Nhận hàng & GRN Kho Tổng',
            href: '/inventory/central-warehouse/receiving',
            icon: PackageCheck,
            feature: 'inventory_basic',
        },
        {
            title: 'Bảng giá nguyên liệu',
            href: '/inventory/central-warehouse/prices',
            icon: BadgeDollarSign,
            permission: 'warehouse.manage',
            feature: 'inventory_basic',
        },
        {
            title: 'Điều phối & Đội ngũ Kho',
            href: '/warehouse/team',
            icon: Users,
            permission: 'warehouse.staff.view',
            feature: 'inventory_basic',
        },
        {
            title: 'Cổng nhân sự Kho',
            href: '/inventory/staff-portal',
            icon: CheckSquare,
            feature: 'inventory_basic',
        },
        {
            title: 'Nhân sự & Lịch biểu',
            href: '/employees',
            icon: UserCheck,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Thưởng nhân viên',
            href: '/bonuses',
            icon: Gift,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Tăng ca',
            href: '/overtime-requests',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
        },
        {
            title: 'Central Kitchen Sơ chế',
            href: '/inventory/central-kitchen',
            icon: Boxes,
            feature: 'inventory_basic',
        },
        {
            title: 'Chuyến xe Logistics',
            href: '/inventory/delivery-manifests',
            icon: Route,
            feature: 'inventory_basic',
        },
        {
            title: 'Thu hồi Lô Khẩn cấp',
            href: '/inventory/batch-recalls',
            icon: Siren,
            feature: 'inventory_basic',
        },
        {
            title: 'Cách ly & Hoàn trả',
            href: '/inventory/reverse-logistics',
            icon: RotateCcw,
            feature: 'inventory_basic',
        },
        {
            title: 'Quản trị Siết chặt Kho',
            href: '/inventory/warehouse-governance',
            icon: ShieldAlert,
            feature: 'inventory_basic',
        },
        {
            title: 'Tồn kho Kho Tổng',
            href: '/inventory/central-warehouse/stock',
            icon: Package,
            feature: 'inventory_basic',
        },
        {
            title: 'Kiểm kê & Điều chỉnh',
            href: '/inventory/count-sessions',
            icon: ClipboardCheck,
            feature: 'inventory_basic',
        },
        {
            title: 'Chốt nguyên liệu',
            href: '/inventory/central-warehouse/material-closing',
            icon: ClipboardCheck,
            feature: 'inventory_basic',
        },
        {
            title: 'Điều chuyển Nội bộ',
            href: '/inventory/transfers',
            icon: ArrowLeftRight,
            feature: 'inventory_basic',
        },
        {
            title: 'Vi phạm nội bộ',
            href: '/violations',
            icon: FileSearch2,
            feature: 'fraud_detection',
        },
        { title: 'Liên hệ & Hỗ trợ', href: '/support', icon: Headset },
    ];

    return nav.filter(
        (item) => !item.feature || canFeature(item.feature as any),
    );
});

const warehouseStaffNav = computed<NavItem[]>(() => {
    const nav = [
        // ─── Tổng quan ────────────────────────────────────────────────────────
        {
            title: 'Cổng tác vụ của tôi',
            href: '/inventory/staff-portal',
            icon: CheckSquare,
            feature: 'inventory_basic',
            section: 'my_work',
        },
        // ─── Kho & Cung ứng ───────────────────────────────────────────────────
        {
            title: 'Điều phối Kho Tổng',
            href: '/inventory/central-warehouse',
            icon: Truck,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Nhận hàng & GRN',
            href: '/inventory/central-warehouse/receiving',
            icon: PackageCheck,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Tồn kho Kho Tổng',
            href: '/inventory/central-warehouse/stock',
            icon: Package,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Kiểm kê & Điều chỉnh',
            href: '/inventory/count-sessions',
            icon: ClipboardCheck,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Chuyến xe Logistics',
            href: '/inventory/delivery-manifests',
            icon: Route,
            feature: 'inventory_basic',
            section: 'supply',
        },
        {
            title: 'Central Kitchen Sơ chế',
            href: '/inventory/central-kitchen',
            icon: Boxes,
            feature: 'inventory_basic',
            section: 'supply',
        },
        // ─── Nhân sự & Hiệu suất ──────────────────────────────────────────────
        {
            title: 'Tăng ca',
            href: '/overtime-requests',
            icon: CalendarDays,
            feature: 'hr_timekeeping',
            section: 'people',
        },
        {
            title: 'Tố cáo ẩn danh',
            href: '/violations',
            icon: ShieldAlert,
            feature: 'fraud_detection',
            section: 'people',
        },
        // ─── Hỗ trợ ───────────────────────────────────────────────────────────
        {
            title: 'Liên hệ & Hỗ trợ',
            href: '/support',
            icon: Headset,
            section: 'settings',
        },
    ];

    const sectionByHref: Record<string, string> = {
        '/inventory/staff-portal': 'my_work',
        '/inventory/central-warehouse': 'supply',
        '/inventory/central-warehouse/receiving': 'supply',
        '/inventory/central-warehouse/stock': 'supply',
        '/inventory/count-sessions': 'supply',
        '/inventory/delivery-manifests': 'supply',
        '/inventory/central-kitchen': 'supply',
        '/overtime-requests': 'people',
        '/violations': 'people',
        '/support': 'settings',
    };

    return nav
        .filter((item) => !item.feature || canFeature(item.feature as any))
        .map((item) => ({
            ...item,
            section: sectionByHref[String(item.href)] ?? item.section,
        }));
});

const shipperNav: NavItem[] = [
    { title: 'Giao hàng của tôi', href: '/delivery/shipper', icon: Route },
    { title: 'Lịch làm việc', href: '/schedules', icon: CalendarDays },
];

// ─── SUPPLIER PORTAL MENU ────────────────────────────────────────────────────
const supplierNav: NavItem[] = [
    { title: 'Tổng quan', href: '/supplier/dashboard', icon: LayoutGrid },
    { title: 'Danh mục & Niêm yết', href: '/supplier/catalog', icon: Package },
    {
        title: 'Đơn đặt hàng (PO)',
        href: '/supplier/orders',
        icon: ShoppingCart,
    },
    { title: 'Đấu thầu RFP', href: '/supplier/rfps', icon: ScrollText },
];

const mainNavItems = computed<NavItem[]>(() => {
    let items: NavItem[] = [];

    if (isSuperAdmin.value) {
        items = superAdminNav.value;
    } else if (isSupplier.value && supplierPortalEnabled.value) {
        items = supplierNav;
    } else if (isOperationsInspector.value) {
        items = operationsInspectorNav;
    } else if (isWarehouseManager.value) {
        items = [...warehouseManagerNav.value];
    } else if (isWarehouseStaff.value) {
        items = [...warehouseStaffNav.value];
    } else if (isShipper.value) {
        items = shipperNav;
    } else if (!isSubscriptionActive.value) {
        items = [{ title: 'Tổng quan', href: '/dashboard', icon: LayoutGrid }];
    } else if (isOwner.value) {
        items = [...ownerNav.value];
    } else if (isManager.value) {
        items = [...managerNav.value];
    } else if (isCashier.value) {
        items = [...cashierNav.value];
    } else if (isWaiter.value) {
        items = [...waiterNav.value];
    } else if (isKitchen.value) {
        items = [...kitchenNav.value];
    } else if (isInventory.value) {
        items = [...inventoryNav.value];
    }

    // Hide supplier-facing and supplier-entry navigation while the external
    // portal is disabled. Internal supplier routes remain available for
    // controlled/direct operational use.
    if (!supplierPortalEnabled.value) {
        items = items.filter(
            (item) => item.href !== '/suppliers' && item.href !== '/rfps',
        );
    }

    items = items.filter((item) => {
        const perm = (item as any).permission;

        if (perm && !can(perm)) {
            return false;
        }

        return true;
    });

    const showPortalLink =
        !isSuperAdmin.value &&
        !isSupplier.value &&
        !isOwner.value &&
        !isOperationsInspector.value &&
        !isWarehouseManager.value &&
        !isCashier.value &&
        !isWaiter.value;

    if (showPortalLink && items.length > 0) {
        if (
            items[0] &&
            (items[0].title === 'Tổng quan' || items[0].title === 'Trang chủ')
        ) {
            const first = items[0];
            const rest = items.slice(1);
            items = [
                first,
                {
                    title: 'Cổng nhân sự',
                    href: '/employee-portal',
                    icon: UserCheck,
                },
                ...rest,
            ];
        } else {
            items = [
                {
                    title: 'Cổng nhân sự',
                    href: '/employee-portal',
                    icon: UserCheck,
                },
                ...items,
            ];
        }
    }

    return items;
});

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="border-b border-sidebar-border/60 p-3">
            <SidebarMenu class="gap-0">
                <SidebarMenuItem>
                    <div class="flex items-center gap-2">
                        <SidebarMenuButton
                            size="lg"
                            as-child
                            class="min-w-0 flex-1 rounded-xl px-2 hover:bg-sidebar-accent/50"
                        >
                            <Link
                                :href="
                                    isSuperAdmin
                                        ? superAdminDashboard().url
                                        : dashboard()
                                "
                            >
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                        <SidebarTrigger
                            class="shrink-0 rounded-lg text-sidebar-foreground/60 hover:bg-sidebar-accent hover:text-sidebar-foreground"
                        />
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-0">
            <NavMain
                v-if="mainNavItems.length"
                :items="mainNavItems"
                :collapsible-groups="usesManagementSidebar"
                :enable-search="usesManagementSidebar"
                :disable-grouping="isCashier || isWaiter || isKitchen"
                :hide-group-label="isCashier || isWaiter || isKitchen"
            />
            <div v-else class="px-4 py-6 text-xs text-muted-foreground">
                Không có menu khả dụng cho tài khoản này.
            </div>

            <!-- Widget hạn ngạch & dùng thử chuyên nghiệp -->
            <SubscriptionWidget v-if="tenant && isOwner" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="footerNavItems.length" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
