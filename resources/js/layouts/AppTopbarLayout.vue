<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Menu, Monitor, Settings, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppearanceToggleInline from '@/components/AppearanceToggleInline.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

const page = usePage();
const user = computed(() => (page.props.auth?.user as User | null) ?? null);
const roles = computed(() => (page.props.auth?.roles as string[]) ?? []);
const isStaff = computed(() => roles.value.length > 0);
const isMobileOpen = ref(false);

const { getInitials } = useInitials();

const navItems = [
    { label: 'TÃ­nh nÄƒng', href: '#features' },
    { label: 'Báº£ng giÃ¡', href: '#pricing' },
    { label: 'Tin tá»©c', href: '/tin-tuc' },
];

const handleLogout = () => {
    router.flushAll();
    router.post(
        '/logout',
        {},
        {
            onSuccess: () => router.visit('/', { replace: true }),
        },
    );
};
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-border bg-background/95 backdrop-blur"
    >
        <div
            class="mx-auto flex max-w-7xl items-center gap-4 px-4 py-3 lg:px-8"
        >
            <Link href="/" class="flex items-center gap-2 font-semibold">
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-sm text-primary-foreground"
                    >A</span
                >
                <span>Aventura</span>
            </Link>

            <nav class="hidden flex-1 items-center gap-1 md:flex">
                <a
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
                >
                    {{ item.label }}
                </a>
            </nav>

            <div class="hidden items-center gap-2 md:flex">
                <AppearanceToggleInline />

                <template v-if="!user">
                    <Button as-child variant="outline" size="sm">
                        <Link href="/login">ÄÄƒng nháº­p</Link>
                    </Button>
                    <Button as-child size="sm">
                        <Link href="/register">DÃ¹ng miá»…n phÃ­</Link>
                    </Button>
                </template>

                <DropdownMenu v-else>
                    <DropdownMenuTrigger as-child>
                        <button
                            class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <Avatar class="h-9 w-9 cursor-pointer">
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
                        <DropdownMenuLabel class="p-0 font-normal">
                            <div class="flex items-center gap-3 px-3 py-2">
                                <Avatar class="h-10 w-10 shrink-0">
                                    <AvatarImage
                                        v-if="user.avatar"
                                        :src="user.avatar"
                                        :alt="user.name"
                                    />
                                    <AvatarFallback
                                        class="bg-primary font-semibold text-primary-foreground"
                                    >
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="flex min-w-0 flex-col">
                                    <span
                                        class="truncate text-sm font-medium"
                                        >{{ user.name }}</span
                                    >
                                    <span
                                        class="truncate text-xs text-muted-foreground"
                                        >{{ user.email }}</span
                                    >
                                    <span
                                        v-if="isStaff"
                                        class="mt-0.5 text-xs font-medium text-primary capitalize"
                                    >
                                        {{ roles[0] }}
                                    </span>
                                </div>
                            </div>
                        </DropdownMenuLabel>

                        <DropdownMenuSeparator />

                        <DropdownMenuGroup v-if="isStaff">
                            <DropdownMenuItem as-child>
                                <Link
                                    href="/dashboard"
                                    class="flex cursor-pointer items-center"
                                >
                                    <Monitor class="mr-2 h-4 w-4" />
                                    VÃ o há»‡ thá»‘ng
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link
                                    href="/settings/profile"
                                    class="flex cursor-pointer items-center"
                                >
                                    <Settings class="mr-2 h-4 w-4" />
                                    CÃ i Ä‘áº·t tÃ i khoáº£n
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                        </DropdownMenuGroup>

                        <DropdownMenuItem
                            class="cursor-pointer text-destructive focus:text-destructive"
                            @click="handleLogout"
                        >
                            <LogOut class="mr-2 h-4 w-4" />
                            ÄÄƒng xuáº¥t
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Button
                variant="outline"
                size="icon"
                class="ml-auto md:hidden"
                @click="isMobileOpen = !isMobileOpen"
            >
                <X v-if="isMobileOpen" class="size-4" />
                <Menu v-else class="size-4" />
                <span class="sr-only">Má»Ÿ menu</span>
            </Button>
        </div>

        <div
            v-if="isMobileOpen"
            class="border-t border-border px-4 py-3 md:hidden"
        >
            <nav class="flex flex-col gap-1">
                <a
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    class="rounded-md px-3 py-2 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
                    @click="isMobileOpen = false"
                >
                    {{ item.label }}
                </a>
            </nav>
            <div class="mt-3 flex items-center gap-2">
                <AppearanceToggleInline />
                <template v-if="!user">
                    <Button as-child variant="outline" size="sm" class="flex-1">
                        <Link href="/login">ÄÄƒng nháº­p</Link>
                    </Button>
                    <Button as-child size="sm" class="flex-1">
                        <Link href="/register">DÃ¹ng miá»…n phÃ­</Link>
                    </Button>
                </template>
                <Button v-else as-child size="sm" class="flex-1">
                    <Link href="/dashboard">VÃ o há»‡ thá»‘ng</Link>
                </Button>
            </div>
        </div>
    </header>

    <main>
        <slot />
    </main>
</template>

<style scoped>
main {
    min-height: 80vh;
}
</style>
