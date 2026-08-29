<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ShoppingCart,
    Package,
    BarChart3,
    Users,
    TrendingUp,
    Utensils,
    PlusCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage();
const roles = computed<string[]>(() => (page.props as any).roles ?? []);

const allActions = [
    {
        label: 'Tạo đơn mới',
        description: 'Tạo đơn tại bàn / mang đi',
        icon: PlusCircle,
        href: '/orders/create',
        color: 'emerald',
        roles: ['owner', 'admin', 'manager', 'cashier', 'staff'],
    },
    {
        label: 'Đơn hàng',
        description: 'Xử lý, theo dõi đơn',
        icon: ShoppingCart,
        href: '/orders',
        color: 'violet',
        roles: ['owner', 'admin', 'manager', 'cashier'],
    },
    {
        label: 'Bàn & Khu vực',
        description: 'Sơ đồ, trạng thái bàn',
        icon: Utensils,
        href: '/tables',
        color: 'teal',
        roles: ['owner', 'admin', 'manager', 'cashier', 'staff'],
    },
    {
        label: 'Sản phẩm',
        description: 'Menu & danh mục',
        icon: Package,
        href: '/products',
        color: 'amber',
        roles: ['owner', 'admin', 'manager'],
    },
    {
        label: 'Kho nguyên liệu',
        description: 'Nhập xuất, tồn kho',
        icon: TrendingUp,
        href: '/inventory',
        color: 'emerald',
        roles: ['owner', 'admin', 'manager'],
    },
    {
        label: 'Nhân viên',
        description: 'Ca làm, phân quyền',
        icon: Users,
        href: '/employees',
        color: 'sky',
        roles: ['owner', 'admin'],
    },
    {
        label: 'Báo cáo',
        description: 'Doanh thu, lợi nhuận',
        icon: BarChart3,
        href: '/reports',
        color: 'rose',
        roles: ['owner', 'admin', 'manager'],
    },
];

const quickActions = computed(() => {
    const userRoles = roles.value;

    if (!userRoles || userRoles.length === 0) {
        return allActions.slice(0, 6);
    }

    // Ưu tiên hiển thị các mục thuộc vai trò người dùng
    return allActions
        .filter((action) => action.roles.some((r) => userRoles.includes(r)))
        .slice(0, 6);
});
</script>

<template>
    <section class="space-y-4">
        <h2
            class="flex items-center gap-2 text-base font-extrabold text-slate-800 dark:text-slate-100"
        >
            <span class="size-1.5 rounded-full bg-primary" />
            Truy cập nhanh
        </h2>
        <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 lg:grid-cols-6">
            <Link
                v-for="action in quickActions"
                :key="action.label"
                :href="action.href"
                class="group dark:hover:border-slate-750 flex flex-col items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4 text-center transition-all duration-300 hover:translate-y-[-2px] hover:border-slate-200 hover:shadow-lg dark:border-slate-800/80 dark:bg-slate-900/60"
            >
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-xl border shadow-inner transition-all duration-300 group-hover:scale-110 group-hover:rotate-3"
                    :class="{
                        'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-400':
                            action.color === 'amber',
                        'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400':
                            action.color === 'emerald',
                        'border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/30 dark:bg-sky-950/20 dark:text-sky-400':
                            action.color === 'sky',
                        'dark:text-rose-455 border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/30 dark:bg-rose-950/20':
                            action.color === 'rose',
                        'border-violet-100 bg-violet-50 text-violet-600 dark:border-violet-900/30 dark:bg-violet-950/20 dark:text-violet-400':
                            action.color === 'violet',
                        'border-teal-100 bg-teal-50 text-teal-600 dark:border-teal-900/30 dark:bg-teal-950/20 dark:text-teal-400':
                            action.color === 'teal',
                        'dark:text-slate-450 border-slate-100 bg-slate-50 text-slate-600 dark:border-slate-900/30 dark:bg-slate-950/20':
                            action.color === 'slate',
                    }"
                >
                    <component :is="action.icon" class="size-5" />
                </div>
                <div>
                    <p
                        class="text-sm font-bold text-slate-800 transition-colors group-hover:text-primary dark:text-slate-200"
                    >
                        {{ action.label }}
                    </p>
                    <p
                        class="mt-1 text-[11px] leading-snug font-medium text-slate-400 dark:text-slate-500"
                    >
                        {{ action.description }}
                    </p>
                </div>
            </Link>
        </div>
    </section>
</template>
