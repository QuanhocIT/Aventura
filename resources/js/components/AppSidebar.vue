<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, FolderGit2, LayoutGrid, Building2, BadgeDollarSign, Users, FileSearch2 } from 'lucide-vue-next';
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
import { dashboard as superAdminDashboard } from '@/routes/superadmin';
import type { NavItem } from '@/types';

import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const roles = computed(() => page.props.roles ?? []);
const isSuperAdmin = computed(() => roles.value.includes('admin') || roles.value.includes('super_admin'));

const mainNavItems = computed<NavItem[]>(() =>
    isSuperAdmin.value
        ? [
            {
                title: 'Dashboard',
                href: superAdminDashboard().url,
                icon: LayoutGrid,
            },
            {
                title: 'Nhà hàng',
                href: '/super-admin/restaurants',
                icon: Building2,
            },
            {
                title: 'Gói dịch vụ',
                href: '/super-admin/plans',
                icon: BadgeDollarSign,
            },
            {
                title: 'Tài khoản',
                href: '/super-admin/accounts',
                icon: Users,
            },
            {
                title: 'Audit Log',
                href: '/super-admin/audit-logs',
                icon: FileSearch2,
            },
        ]
        : []
);

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
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
