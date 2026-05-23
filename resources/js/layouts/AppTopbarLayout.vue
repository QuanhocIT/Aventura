<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Monitor, Settings } from 'lucide-vue-next';
import { computed } from 'vue';
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
const user = computed(() => page.props.auth?.user as User | null ?? null);
const roles = computed(() => page.props.auth?.roles as string[] ?? []);
const isStaff = computed(() => roles.value.length > 0);

const { getInitials } = useInitials();

const handleLogout = () => {
    router.flushAll();
    router.post('/logout', {}, {
        onSuccess: () => router.visit('/', { replace: true }),
    });
};
</script>

<template>
    <header class="flex items-center gap-8 px-6 py-4 border-b border-border bg-background">
        <h1 class="font-bold text-lg">Aventura</h1>
        <nav class="flex items-center gap-4 flex-1">
            <a href="#tinh-nang" class="hover:underline px-3 py-2">Tính năng</a>
            <a href="#cach-hoat-dong" class="hover:underline px-3 py-2">Cách hoạt động</a>
            <a href="#bang-gia" class="hover:underline px-3 py-2">Bảng giá</a>
            <div class="flex-1" />
            <AppearanceToggleInline />

            <!-- Chưa đăng nhập -->
            <template v-if="!user">
                <Button as-child variant="outline">
                    <Link href="/login">Đăng nhập</Link>
                </Button>
                <Button as-child>
                    <Link href="/register">Dùng thử miễn phí</Link>
                </Button>
            </template>

            <!-- Đã đăng nhập → avatar dropdown -->
            <DropdownMenu v-else>
                <DropdownMenuTrigger as-child>
                    <button class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <Avatar class="h-9 w-9 cursor-pointer">
                            <AvatarImage
                                v-if="user.avatar"
                                :src="user.avatar"
                                :alt="user.name"
                            />
                            <AvatarFallback class="bg-primary text-primary-foreground font-semibold text-sm">
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </button>
                </DropdownMenuTrigger>

                <DropdownMenuContent class="w-60" align="end" :side-offset="8">
                    <!-- Thông tin tài khoản -->
                    <DropdownMenuLabel class="p-0 font-normal">
                        <div class="flex items-center gap-3 px-3 py-2">
                            <Avatar class="h-10 w-10 shrink-0">
                                <AvatarImage
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                />
                                <AvatarFallback class="bg-primary text-primary-foreground font-semibold">
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="flex flex-col min-w-0">
                                <span class="truncate font-medium text-sm">{{ user.name }}</span>
                                <span class="truncate text-xs text-muted-foreground">{{ user.email }}</span>
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

                    <!-- Staff: vào hệ thống + settings -->
                    <DropdownMenuGroup v-if="isStaff">
                        <DropdownMenuItem as-child>
                            <Link href="/dashboard" class="flex items-center cursor-pointer">
                                <Monitor class="mr-2 h-4 w-4" />
                                Vào hệ thống
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link href="/settings/profile" class="flex items-center cursor-pointer">
                                <Settings class="mr-2 h-4 w-4" />
                                Cài đặt tài khoản
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                    </DropdownMenuGroup>

                    <!-- Đăng xuất -->
                    <DropdownMenuItem class="cursor-pointer text-destructive focus:text-destructive" @click="handleLogout">
                        <LogOut class="mr-2 h-4 w-4" />
                        Đăng xuất
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </nav>
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
