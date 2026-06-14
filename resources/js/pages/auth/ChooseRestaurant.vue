<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
// @ts-ignore
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface RestaurantInfo {
    id: number;
    name: string;
    logo_url: string | null;
    role: string;
    job_title: string;
    user_id: number;
}

defineProps<{
    restaurants: RestaurantInfo[];
}>();

const selectedUserId = ref<number | null>(null);

const selectRestaurant = (userId: number) => {
    selectedUserId.value = userId;
    router.post('/choose-restaurant', { user_id: userId }, {
        onFinish: () => {
            selectedUserId.value = null;
        }
    });
};

// Translate roles for premium Vietnamese labels and style classes
const getRoleBadgeClasses = (role: string) => {
    const r = role.toLowerCase();

    if (r === 'owner' || r === 'chủ quán' || r === 'chủ sở hữu') {
        return 'border-amber-500/25 bg-amber-500/10 text-amber-600 dark:text-amber-400';
    }

    if (r === 'manager' || r === 'quản lý') {
        return 'border-violet-500/25 bg-violet-500/10 text-violet-600 dark:text-violet-400';
    }

    return 'border-emerald-500/25 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
};

const getRoleLabel = (role: string) => {
    const r = role.toLowerCase();

    if (r === 'owner') {
return 'Chủ quán';
}

    if (r === 'manager') {
return 'Quản lý';
}

    if (r === 'cashier') {
return 'Thu ngân';
}

    if (r === 'kitchen') {
return 'Bếp';
}

    return role;
};
</script>

<template>
    <Head title="Chọn nhà hàng · Aventura" />

    <div class="relative min-h-dvh flex items-center justify-center overflow-hidden bg-gradient-to-b from-zinc-50 via-white to-zinc-50 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950 px-6 py-12 sm:px-10 md:px-16">
        <!-- Decorative dynamic glowing elements -->
        <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent" />
        <div class="pointer-events-none absolute -bottom-20 -left-20 h-[450px] w-[450px] rounded-full bg-emerald-500/10 blur-[130px] animate-pulse duration-[8s]" />
        <div class="pointer-events-none absolute -right-20 -top-20 h-[450px] w-[450px] rounded-full bg-blue-500/10 blur-[130px] animate-pulse duration-[6s]" />

        <div class="relative z-10 w-full max-w-4xl mx-auto flex flex-col items-center">
            <!-- App Logo & Title -->
            <div class="flex flex-col items-center gap-3 mb-10 text-center animate-in fade-in slide-in-from-top-6 duration-500">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary shadow-lg ring-4 ring-primary/10">
                    <AppLogoIcon class="size-6 fill-current text-primary-foreground" />
                </span>
                <h1 class="text-3xl sm:text-4xl font-black tracking-tight bg-gradient-to-r from-zinc-900 via-zinc-800 to-emerald-600 dark:from-white dark:via-zinc-200 dark:to-emerald-400 bg-clip-text text-transparent leading-none">
                    Chào mừng bạn đến với Aventura
                </h1>
                <p class="mt-2.5 text-sm sm:text-base text-muted-foreground max-w-md">
                    Địa chỉ email của bạn được liên kết với nhiều nhà hàng. Hãy chọn nơi làm việc cho ca này.
                </p>
            </div>

            <!-- Restaurant Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 w-full animate-in fade-in slide-in-from-bottom-6 duration-700 delay-100">
                <button
                    v-for="rest in restaurants"
                    :key="rest.id"
                    @click="selectRestaurant(rest.user_id)"
                    :disabled="selectedUserId !== null"
                    class="group relative flex flex-col justify-between overflow-hidden text-left cursor-pointer backdrop-blur-xl bg-white/70 dark:bg-zinc-950/70 border border-white/80 dark:border-zinc-800/80 p-6 sm:p-8 rounded-3xl shadow-[0_15px_35px_rgba(0,0,0,0.03)] dark:shadow-[0_15px_35px_rgba(0,0,0,0.2)] transition-all duration-300 hover:border-emerald-500/50 hover:shadow-[0_20px_40px_rgba(16,185,129,0.08)] hover:-translate-y-1 active:scale-[0.98] disabled:opacity-75 disabled:pointer-events-none w-full"
                >
                    <!-- Loading state overlay -->
                    <div v-if="selectedUserId === rest.user_id" class="absolute inset-0 bg-white/50 dark:bg-zinc-950/50 backdrop-blur-sm flex items-center justify-center z-20 transition-all duration-300">
                        <div class="flex flex-col items-center gap-2">
                            <svg class="animate-spin h-8 w-8 text-emerald-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">Đang đăng nhập...</span>
                        </div>
                    </div>

                    <!-- Glowing subtle light highlight on card hover -->
                    <div class="pointer-events-none absolute -right-20 -bottom-20 h-40 w-40 rounded-full bg-emerald-500/5 group-hover:bg-emerald-500/10 blur-xl transition-all duration-300" />

                    <div>
                        <!-- Header with logo and role badge -->
                        <div class="flex items-start justify-between gap-4 mb-6">
                            <!-- Restaurant Image Placeholder or Real logo -->
                            <div class="h-14 w-14 rounded-2xl bg-zinc-100 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 flex items-center justify-center overflow-hidden transition-transform duration-300 group-hover:scale-105 shadow-inner">
                                <img
                                    v-if="rest.logo_url"
                                    :src="rest.logo_url"
                                    class="h-full w-full object-cover"
                                    :alt="rest.name"
                                />
                                <span v-else class="text-lg font-black text-emerald-600 dark:text-emerald-400 uppercase">
                                    {{ rest.name.charAt(0) }}
                                </span>
                            </div>

                            <!-- Role Badge -->
                            <span :class="['inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold shadow-sm transition-all duration-300 group-hover:scale-105', getRoleBadgeClasses(rest.role)]">
                                {{ getRoleLabel(rest.role) }}
                            </span>
                        </div>

                        <!-- Restaurant details -->
                        <h3 class="text-xl font-extrabold tracking-tight text-zinc-900 dark:text-white transition-colors duration-300 group-hover:text-emerald-600 dark:group-hover:text-emerald-400">
                            {{ rest.name }}
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground flex items-center gap-1.5 font-medium">
                            <span class="h-1.5 w-1.5 rounded-full bg-zinc-400 dark:bg-zinc-650" />
                            Chức danh: <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ rest.job_title }}</span>
                        </p>
                    </div>

                    <!-- Action bottom link indicator -->
                    <div class="mt-8 flex items-center gap-1 text-sm font-bold text-emerald-600 dark:text-emerald-400 group-hover:gap-2 transition-all duration-300">
                        Vào nhà hàng
                        <svg class="h-4 w-4 transform transition-transform duration-300 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </button>
            </div>

            <!-- Logout Link -->
            <div class="mt-12 text-center animate-in fade-in slide-in-from-bottom-4 duration-500 delay-200">
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="text-sm font-bold text-muted-foreground hover:text-zinc-900 dark:hover:text-white underline underline-offset-4 hover:decoration-emerald-500 transition-colors cursor-pointer bg-transparent border-none"
                >
                    Đăng xuất tài khoản
                </Link>
            </div>
        </div>
    </div>
</template>
