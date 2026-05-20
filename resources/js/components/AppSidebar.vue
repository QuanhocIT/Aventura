<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen, Building2, FileText, FolderGit2,
    LayoutGrid, ShieldCheck, Layers,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
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
import type { NavItem } from '@/types';

const page = usePage();
const roles = computed(() => page.props.auth?.roles as string[] ?? []);
const isSuperAdmin = computed(() => roles.value.includes('super_admin'));

const superAdminNavItems: NavItem[] = [
    { title: 'Bảng điều khiển', href: '/super-admin/dashboard', icon: LayoutGrid },
    { title: 'Nhà hàng',        href: '/super-admin/restaurants', icon: Building2 },
    { title: 'Gói dịch vụ',    href: '/super-admin/plans',       icon: Layers },
    { title: 'Tài khoản',      href: '/super-admin/accounts',    icon: ShieldCheck },
    { title: 'Audit Log',      href: '/super-admin/audit-logs',  icon: FileText },
];

const defaultNavItems: NavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
];

const mainNavItems = computed(() => isSuperAdmin.value ? superAdminNavItems : defaultNavItems);

const footerNavItems: NavItem[] = [
    { title: 'Repository',     href: 'https://github.com/laravel/vue-starter-kit', icon: FolderGit2 },
    { title: 'Documentation',  href: 'https://laravel.com/docs/starter-kits#vue',  icon: BookOpen },
];

const homeHref = computed(() => isSuperAdmin.value ? '/super-admin/dashboard' : dashboard());
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="!isSuperAdmin" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
