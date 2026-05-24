<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    LayoutGrid,
    Bot,
    Building2,
    BadgeDollarSign,
    Image,
    Newspaper,
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
    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});

const hasRole = (...roleNames: string[]) =>
    roles.value.some((r: string) => roleNames.includes(r));

const isSuperAdmin  = computed(() => hasRole('admin', 'super_admin'));
const isOwner       = computed(() => hasRole('owner'));
const isManager     = computed(() => hasRole('manager'));
const isCashier     = computed(() => hasRole('cashier'));
const isKitchen     = computed(() => hasRole('kitchen'));
const isInventory   = computed(() => hasRole('inventory_staff'));
const isCustomer    = computed(() => hasRole('customer'));

// â”€â”€â”€ SUPER ADMIN MENU â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const superAdminNav: NavItem[] = [
    { title: 'Dashboard',      href: superAdminDashboard().url, icon: LayoutGrid },
    { title: 'NhÃ  hÃ ng',       href: '/super-admin/restaurants', icon: Building2 },
    { title: 'GÃ³i dá»‹ch vá»¥',   href: '/super-admin/plans',       icon: BadgeDollarSign },
    { title: 'TÃ i khoáº£n',      href: '/super-admin/accounts',    icon: Users },
    { title: 'Billing Center', href: '/super-admin/billing',     icon: BadgeDollarSign },
    { title: 'Banner & Slideshow', href: '/super-admin/banners', icon: Image },
    { title: 'Audit Log',      href: '/super-admin/audit-logs',  icon: FileSearch2 },
    { title: 'DevOps & Support', href: '/super-admin/support',  icon: Headset },
    { title: 'Chatbot AI',      href: '/super-admin/chatbot',   icon: Bot },
    { title: 'Tin tá»©c',         href: '/super-admin/news',      icon: Newspaper },
];

// â”€â”€â”€ OWNER MENU â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const ownerNav: NavItem[] = [
    { title: 'Tá»•ng quan',        href: '/dashboard',              icon: LayoutGrid },
    { title: 'Quáº£n lÃ½ Ä‘Æ¡n hÃ ng', href: '/orders',                 icon: ShoppingCart },
    { title: 'Thá»±c Ä‘Æ¡n & MÃ³n',   href: '/products',               icon: UtensilsCrossed },
    { title: 'Kho nguyÃªn liá»‡u',  href: '/inventory',              icon: Package },
    { title: 'NhÃ  cung cáº¥p',     href: '/suppliers',              icon: Truck },
    { title: 'NhÃ¢n sá»±',          href: '/employees',              icon: UserCheck },
    { title: 'Cháº¥m cÃ´ng & Lá»‹ch', href: '/schedules',              icon: CalendarDays },
    { title: 'Báº£ng lÆ°Æ¡ng',       href: '/salaries',               icon: Wallet },
    { title: 'KhÃ¡ch hÃ ng',       href: '/customers',              icon: Users },
    { title: 'Khuyáº¿n mÃ£i',       href: '/promotions',             icon: Tag },
    { title: 'BÃ¡o cÃ¡o & AI',     href: '/reports',                icon: BarChart3 },
    { title: 'SÆ¡ Ä‘á»“ bÃ n',        href: '/tables',                 icon: Building2 },
    { title: 'Audit Log',        href: '/audit-logs',             icon: ScrollText },
    { title: 'Tin tá»©c',          href: '/tin-tuc',                icon: Newspaper },
    { title: 'LiÃªn há»‡ & Há»— trá»£', href: '/support',                icon: Headset },
];

// â”€â”€â”€ MANAGER MENU â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const managerNav: NavItem[] = [
    { title: 'Tá»•ng quan',        href: '/dashboard',              icon: LayoutGrid },
    { title: 'ÄÆ¡n hÃ ng hÃ´m nay', href: '/orders',                 icon: ShoppingCart },
    { title: 'NhÃ¢n sá»±',          href: '/employees',              icon: UserCheck },
    { title: 'Cháº¥m cÃ´ng & Lá»‹ch', href: '/schedules',              icon: CalendarDays },
    { title: 'Báº£ng lÆ°Æ¡ng',       href: '/salaries',               icon: Wallet },
    { title: 'Khuyáº¿n mÃ£i',       href: '/promotions',             icon: Tag },
    { title: 'Pháº£n há»“i KH',      href: '/feedback',               icon: MessageSquare },
    { title: 'BÃ¡o cÃ¡o doanh thu', href: '/reports',               icon: BarChart3 },
    { title: 'Vi pháº¡m ná»™i bá»™',   href: '/violations',             icon: FileSearch2 },
    { title: 'Tin tá»©c',          href: '/tin-tuc',                icon: Newspaper },
    { title: 'LiÃªn há»‡ & Há»— trá»£', href: '/support',                icon: Headset },
];

// â”€â”€â”€ CASHIER MENU â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const cashierNav: NavItem[] = [
    { title: 'Táº¡o Ä‘Æ¡n hÃ ng',    href: '/orders/create',           icon: ShoppingCart },
    { title: 'SÆ¡ Ä‘á»“ bÃ n',       href: '/tables',                  icon: Building2 },
    { title: 'Lá»‹ch sá»­ Ä‘Æ¡n',     href: '/orders',                  icon: ScrollText },
    { title: 'Doanh thu ca',     href: '/reports/shift',           icon: Wallet },
    { title: 'Lá»‹ch lÃ m viá»‡c',   href: '/schedules',               icon: CalendarDays },
];

// â”€â”€â”€ KITCHEN MENU â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const kitchenNav: NavItem[] = [
    { title: 'MÃ n hÃ¬nh báº¿p',    href: '/kitchen',                  icon: ChefHat },
    { title: 'Lá»‹ch lÃ m viá»‡c',   href: '/schedules',                icon: CalendarDays },
];

// â”€â”€â”€ INVENTORY STAFF MENU â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const inventoryNav: NavItem[] = [
    { title: 'Tá»“n kho',         href: '/inventory',                icon: Package },
    { title: 'Nháº­p kho',        href: '/inventory/receive',        icon: Truck },
    { title: 'Lá»‹ch sá»­ giao dá»‹ch', href: '/inventory/transactions', icon: Clock },
    { title: 'NhÃ  cung cáº¥p',    href: '/suppliers',                icon: Truck },
    { title: 'Lá»‹ch lÃ m viá»‡c',   href: '/schedules',                icon: CalendarDays },
];

// â”€â”€â”€ CUSTOMER MENU â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const customerNav: NavItem[] = [
    { title: 'Thá»±c Ä‘Æ¡n',        href: '/menu',                     icon: UtensilsCrossed },
    { title: 'ÄÆ¡n hÃ ng cá»§a tÃ´i', href: '/my-orders',               icon: ShoppingCart },
    { title: 'Khuyáº¿n mÃ£i',      href: '/promotions',               icon: Tag },
    { title: 'Pháº£n há»“i',        href: '/feedback',                 icon: MessageSquare },
];

// â”€â”€â”€ Chá»n menu dá»±a trÃªn role vÃ  háº¡n gÃ³i â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
const mainNavItems = computed<NavItem[]>(() => {
    if (isSuperAdmin.value) return superAdminNav;
    
    // Náº¿u gÃ³i háº¿t háº¡n / bá»‹ khÃ³a, giá»›i háº¡n chá»‰ cho phÃ©p xem Tá»•ng quan
    if (!isSubscriptionActive.value) {
        return [
            { title: 'Tá»•ng quan', href: '/dashboard', icon: LayoutGrid }
        ];
    }
    
    if (isOwner.value)      return ownerNav;
    if (isManager.value)    return managerNav;
    if (isCashier.value)    return cashierNav;
    if (isKitchen.value)    return kitchenNav;
    if (isInventory.value)  return inventoryNav;
    if (isCustomer.value)   return customerNav;
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
                KhÃ´ng cÃ³ menu kháº£ dá»¥ng cho tÃ i khoáº£n nÃ y.
            </div>
            
            <!-- Widget háº¡n ngáº¡ch & dÃ¹ng thá»­ chuyÃªn nghiá»‡p -->
            <SubscriptionWidget v-if="tenant && !isSuperAdmin" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
