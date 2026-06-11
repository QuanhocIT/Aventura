<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppearanceToggleInline from '@/components/AppearanceToggleInline.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
        </div>

        <div class="flex items-center gap-4">
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
