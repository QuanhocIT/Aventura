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
    router.post(
        route('choose-restaurant.select'),
        { user_id: userId },
        {
            onFinish: () => {
                selectedUserId.value = null;
            },
        },
    );
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

    <div
        class="relative flex min-h-dvh items-center justify-center overflow-hidden bg-gradient-to-b from-zinc-50 via-white to-zinc-50 px-6 py-12 sm:px-10 md:px-16 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950"
    >
        <!-- Decorative dynamic glowing elements -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"
        />
        <div
            class="pointer-events-none absolute -bottom-20 -left-20 h-[450px] w-[450px] animate-pulse rounded-full bg-emerald-500/10 blur-[130px] duration-[8s]"
        />
        <div
            class="pointer-events-none absolute -top-20 -right-20 h-[450px] w-[450px] animate-pulse rounded-full bg-blue-500/10 blur-[130px] duration-[6s]"
        />

        <div
            class="relative z-10 mx-auto flex w-full max-w-4xl flex-col items-center"
        >
            <!-- App Logo & Title -->
            <div
                class="mb-10 flex animate-in flex-col items-center gap-3 text-center duration-500 fade-in slide-in-from-top-6"
            >
                <span
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary shadow-lg ring-4 ring-primary/10"
                >
                    <AppLogoIcon
                        class="size-6 fill-current text-primary-foreground"
                    />
                </span>
                <h1
                    class="bg-gradient-to-r from-zinc-900 via-zinc-800 to-emerald-600 bg-clip-text text-3xl leading-none font-black tracking-tight text-transparent sm:text-4xl dark:from-white dark:via-zinc-200 dark:to-emerald-400"
                >
                    Chào mừng bạn đến với Aventura
                </h1>
                <p
                    class="mt-2.5 max-w-md text-sm text-muted-foreground sm:text-base"
                >
                    Tài khoản của bạn được liên kết với nhiều nhà hàng hoặc chi nhánh. Vui lòng chọn nơi làm việc để tiếp tục.
                </p>
            </div>

            <!-- Restaurant Grid -->
            <div
                class="grid w-full animate-in grid-cols-1 gap-6 delay-100 duration-700 fade-in slide-in-from-bottom-6 sm:grid-cols-2"
            >
                <button
                    v-for="rest in restaurants"
                    :key="rest.id"
                    @click="selectRestaurant(rest.user_id)"
                    :disabled="selectedUserId !== null"
                    class="group relative flex w-full cursor-pointer flex-col justify-between overflow-hidden rounded-3xl border border-white/80 bg-white/70 p-6 text-left shadow-[0_15px_35px_rgba(0,0,0,0.03)] backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/50 hover:shadow-[0_20px_40px_rgba(16,185,129,0.08)] active:scale-[0.98] disabled:pointer-events-none disabled:opacity-75 sm:p-8 dark:border-zinc-800/80 dark:bg-zinc-950/70 dark:shadow-[0_15px_35px_rgba(0,0,0,0.2)]"
                >
                    <!-- Loading state overlay -->
                    <div
                        v-if="selectedUserId === rest.user_id"
                        class="absolute inset-0 z-20 flex items-center justify-center bg-white/50 backdrop-blur-sm transition-all duration-300 dark:bg-zinc-950/50"
                    >
                        <div class="flex flex-col items-center gap-2">
                            <svg
                                class="h-8 w-8 animate-spin text-emerald-500"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            <span
                                class="text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                                >Đang đăng nhập...</span
                            >
                        </div>
                    </div>

                    <!-- Glowing subtle light highlight on card hover -->
                    <div
                        class="pointer-events-none absolute -right-20 -bottom-20 h-40 w-40 rounded-full bg-emerald-500/5 blur-xl transition-all duration-300 group-hover:bg-emerald-500/10"
                    />

                    <div>
                        <!-- Header with logo and role badge -->
                        <div
                            class="mb-6 flex items-start justify-between gap-4"
                        >
                            <!-- Restaurant Image Placeholder or Real logo -->
                            <div
                                class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-100 shadow-inner transition-transform duration-300 group-hover:scale-105 dark:border-zinc-800 dark:bg-zinc-900"
                            >
                                <img
                                    v-if="rest.logo_url"
                                    :src="rest.logo_url"
                                    class="h-full w-full object-cover"
                                    :alt="rest.name"
                                />
                                <span
                                    v-else
                                    class="text-lg font-black text-emerald-600 uppercase dark:text-emerald-400"
                                >
                                    {{ rest.name.charAt(0) }}
                                </span>
                            </div>

                            <!-- Role Badge -->
                            <span
                                :class="[
                                    'inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold shadow-sm transition-all duration-300 group-hover:scale-105',
                                    getRoleBadgeClasses(rest.role),
                                ]"
                            >
                                {{ getRoleLabel(rest.role) }}
                            </span>
                        </div>

                        <!-- Restaurant details -->
                        <h3
                            class="text-xl font-extrabold tracking-tight text-zinc-900 transition-colors duration-300 group-hover:text-emerald-600 dark:text-white dark:group-hover:text-emerald-400"
                        >
                            {{ rest.name }}
                        </h3>
                        <p
                            class="mt-2 flex items-center gap-1.5 text-sm font-medium text-muted-foreground"
                        >
                            <span
                                class="dark:bg-zinc-650 h-1.5 w-1.5 rounded-full bg-zinc-400"
                            />
                            Chức danh:
                            <span
                                class="font-bold text-zinc-800 dark:text-zinc-200"
                                >{{ rest.job_title }}</span
                            >
                        </p>
                    </div>

                    <!-- Action bottom link indicator -->
                    <div
                        class="mt-8 flex items-center gap-1 text-sm font-bold text-emerald-600 transition-all duration-300 group-hover:gap-2 dark:text-emerald-400"
                    >
                        Vào nhà hàng
                        <svg
                            class="h-4 w-4 transform transition-transform duration-300 group-hover:translate-x-0.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 5l7 7-7 7"
                            />
                        </svg>
                    </div>
                </button>
            </div>

            <!-- Logout Link -->
            <div
                class="mt-12 animate-in text-center delay-200 duration-500 fade-in slide-in-from-bottom-4"
            >
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="cursor-pointer border-none bg-transparent text-sm font-bold text-muted-foreground underline underline-offset-4 transition-colors hover:text-zinc-900 hover:decoration-emerald-500 dark:hover:text-white"
                >
                    Đăng xuất tài khoản
                </Link>
            </div>
        </div>
    </div>
</template>
