<script setup lang="ts">
import { Link, usePage, router } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';
import AppearanceToggleInline from '@/components/AppearanceToggleInline.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
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
const flash = computed(() => (page.props as any).flash ?? {});
const hasFlash = computed(() => !!(flash.value.success || flash.value.error));

const navItems = [
    { label: 'Dashboard', href: '/dashboard' },
    { label: 'Sản phẩm', href: '/products' },
    { label: 'Kho', href: '/inventory' },
    { label: 'Nhân viên', href: '/employees' },
    { label: 'Hỗ trợ', href: '/support' },
];

const isSuperAdminRoute = computed(() => page.url.startsWith('/super-admin'));

const tenant = computed(() => page.props.tenant as any);
const branches = computed(() => tenant.value?.branches ?? []);
const activeBranchId = computed(() => tenant.value?.active_branch_id ?? null);

const handleBranchChange = (e: Event) => {
    const val = (e.target as HTMLSelectElement).value;
    router.post('/branch/switch', { branch_id: parseInt(val) });
};
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
            <nav v-else-if="user && !isSuperAdminRoute" class="hidden items-center gap-0.5 md:flex">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="rounded-md px-3 py-1.5 text-sm transition-colors"
                    :class="page.url.startsWith(item.href)
                        ? 'bg-muted text-foreground font-medium'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                >
                    {{ item.label }}
                </Link>
            </nav>
        </div>

        <div class="flex items-center gap-4">
            <!-- Branch context switcher -->
            <div v-if="branches && branches.length > 1" class="flex items-center gap-1.5 mr-2">
                <span class="text-[11px] text-muted-foreground hidden sm:inline font-medium uppercase tracking-wider">Chi nhánh:</span>
                <select
                    :value="activeBranchId"
                    @change="handleBranchChange"
                    class="h-8 rounded-lg border border-border bg-background px-2.5 py-1 text-xs font-semibold shadow-sm transition-all focus:outline-none focus:ring-1 focus:ring-ring hover:bg-accent cursor-pointer text-slate-800 dark:text-slate-200"
                >
                    <option v-for="b in branches" :key="b.id" :value="b.id">
                        {{ b.name }}
                    </option>
                </select>
            </div>

            <AppearanceToggleInline />

            <!-- Flash notification indicator -->
            <button
                v-if="user"
                class="relative rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-muted transition-colors cursor-pointer"
                aria-label="Thông báo"
            >
                <Bell class="size-4" />
                <span
                    v-if="hasFlash"
                    class="absolute right-1.5 top-1.5 h-1.5 w-1.5 rounded-full bg-rose-500"
                />
            </button>

            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <button
                        class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
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

                <DropdownMenuContent
                    class="w-60"
                    align="end"
                    :side-offset="8"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
