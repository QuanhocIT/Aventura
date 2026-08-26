<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Settings, Star, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PlatformFeedbackModal from '@/components/PlatformFeedbackModal.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user?: User | null;
};

defineProps<Props>();

const page = usePage();
const roles = computed(() => {
    const raw = page.props.roles ?? [];

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});
const isOwner = computed(() => roles.value.includes('owner'));
const isOwnerOrSuperAdmin = computed(() =>
    roles.value.some((role) =>
        ['owner', 'super_admin', 'system_admin'].includes(role),
    ),
);

const showFeedbackModal = ref(false);

const handleLogout = () => {
    router.flushAll();
    router.post(
        logout.url(),
        {},
        {
            onSuccess: () => router.visit('/login', { replace: true }),
        },
    );
};
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo v-if="user" :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="isOwnerOrSuperAdmin ? edit() : '/settings/profile'"
                prefetch
            >
                <Settings v-if="isOwnerOrSuperAdmin" class="mr-2 h-4 w-4" />
                <UserRound v-else class="mr-2 h-4 w-4" />
                {{ isOwnerOrSuperAdmin ? 'Cài đặt hệ thống' : 'Hồ sơ cá nhân' }}
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem
            v-if="isOwner"
            class="cursor-pointer font-semibold text-amber-600 dark:text-amber-400"
            @click="showFeedbackModal = true"
        >
            <Star class="mr-2 h-4 w-4 fill-amber-400 text-amber-400" />
            Đánh giá gói dịch vụ
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem
        class="cursor-pointer"
        data-test="logout-button"
        @click="handleLogout"
    >
        <LogOut class="mr-2 h-4 w-4" />
        Log out
    </DropdownMenuItem>

    <PlatformFeedbackModal v-if="isOwner" v-model:open="showFeedbackModal" />
</template>
