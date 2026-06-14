<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ShoppingCart,
    Package,
    BarChart3,
    Users,
    TrendingUp
} from 'lucide-vue-next';

const quickActions = [
    { label: 'Đơn hàng',        description: 'Xử lý, theo dõi đơn',  icon: ShoppingCart, href: '/orders',          color: 'violet' },
    { label: 'Sản phẩm',        description: 'Menu & danh mục',       icon: Package,      href: '/products',        color: 'amber' },
    { label: 'Bàn & Khu vực',   description: 'Sơ đồ, trạng thái bàn', icon: BarChart3,    href: '/tables',          color: 'teal' },
    { label: 'Nhân viên',        description: 'Ca làm, phân quyền',    icon: Users,        href: '/employees',       color: 'sky' },
    { label: 'Kho nguyên liệu',  description: 'Nhập xuất, tồn kho',   icon: TrendingUp,   href: '/inventory',       color: 'emerald' },
    { label: 'Báo cáo',          description: 'Doanh thu, lợi nhuận',  icon: BarChart3,    href: '/reports',         color: 'rose' },
];
</script>

<template>
    <section>
        <h2 class="text-base font-semibold">Truy cập nhanh</h2>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <Link
                v-for="action in quickActions"
                :key="action.label"
                :href="action.href"
                class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-card p-4 text-center transition-all hover:border-primary/30 hover:bg-muted/40 hover:shadow-sm"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl border transition-all group-hover:scale-110"
                    :class="{
                        'border-amber-100   bg-amber-50   text-amber-600   dark:border-amber-900/30   dark:bg-amber-950/20   dark:text-amber-400':   action.color === 'amber',
                        'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400': action.color === 'emerald',
                        'border-sky-100     bg-sky-50     text-sky-600     dark:border-sky-900/30     dark:bg-sky-950/20     dark:text-sky-400':     action.color === 'sky',
                        'border-rose-100    bg-rose-50    text-rose-600    dark:border-rose-900/30    dark:bg-rose-950/20    dark:text-rose-400':    action.color === 'rose',
                        'border-violet-100  bg-violet-50  text-violet-600  dark:border-violet-900/30  dark:bg-violet-950/20  dark:text-violet-400':  action.color === 'violet',
                        'border-teal-100    bg-teal-50    text-teal-600    dark:border-teal-900/30    dark:bg-teal-950/20    dark:text-teal-400':    action.color === 'teal',
                        'border-slate-100   bg-slate-50   text-slate-600   dark:border-slate-900/30   dark:bg-slate-950/20   dark:text-slate-400':   action.color === 'slate',
                    }"
                >
                    <component :is="action.icon" class="size-5" />
                </div>
                <div>
                    <p class="text-sm font-semibold">{{ action.label }}</p>
                    <p class="mt-0.5 text-xs leading-snug text-muted-foreground">{{ action.description }}</p>
                </div>
            </Link>
        </div>
    </section>
</template>
