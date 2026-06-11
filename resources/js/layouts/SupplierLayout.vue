<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Toaster } from '@/components/ui/sonner';
import FlashToast from '@/components/FlashToast.vue';
import { LayoutGrid, ListOrdered, TrendingUp, LogOut, Building2, ShoppingCart } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import echo from '@/lib/echo';

const page = usePage();
const user = computed(() => (page.props.auth?.user as any) ?? null);

// Badge: số PO pending chưa được tiếp nhận
const pendingPoCount = ref<number>(
    (page.props as any).pendingPoCount ?? 0
);

const navItems = computed(() => [
    { label: 'Tổng quan', href: '/supplier-portal/dashboard', icon: LayoutGrid, badge: 0 },
    { label: 'Danh mục hàng hoá', href: '/supplier-portal/catalog', icon: ListOrdered, badge: 0 },
    { label: 'Lịch sử giá', href: '/supplier-portal/price-history', icon: TrendingUp, badge: 0 },
    { label: 'Đơn đặt hàng', href: '/supplier-portal/purchase-orders', icon: ShoppingCart, badge: pendingPoCount.value },
]);

const currentPath = computed(() => page.url);
const isActive = (href: string) => currentPath.value.startsWith(href);

// Lắng nghe PO mới qua Reverb
let channel: ReturnType<typeof echo.private> | null = null;

onMounted(() => {
    const supplierId = user.value?.supplier_id;
    if (!supplierId) return;

    channel = echo.private(`supplier.${supplierId}`);
    channel.listen('.purchase-order.created', (data: any) => {
        pendingPoCount.value++;
        toast.info(`Đơn hàng mới: ${data.po_number}`, {
            description: `Tổng trị giá: ${Number(data.total_amount).toLocaleString('vi-VN')} ₫`,
            action: {
                label: 'Xem ngay',
                onClick: () => { window.location.href = '/supplier-portal/purchase-orders'; },
            },
        });
    });
});

onUnmounted(() => {
    const supplierId = user.value?.supplier_id;
    if (supplierId && channel) {
        echo.leave(`supplier.${supplierId}`);
    }
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex">
        <!-- Sidebar -->
        <aside class="w-64 shrink-0 bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col">
            <!-- Logo / Brand -->
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-2">
                    <Building2 class="size-6 text-blue-600" />
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 leading-none">Cổng nhà cung cấp</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-white leading-tight mt-0.5">Supplier Portal</p>
                    </div>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors"
                    :class="isActive(item.href)
                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-400'
                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
                >
                    <component :is="item.icon" class="size-4 shrink-0" />
                    <span class="flex-1">{{ item.label }}</span>
                    <span
                        v-if="item.badge > 0"
                        class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 text-xs font-bold text-white bg-red-500 rounded-full"
                    >{{ item.badge }}</span>
                </Link>
            </nav>

            <!-- User info + logout -->
            <div class="px-4 py-4 border-t border-gray-200 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-3">
                    <div class="size-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-700 dark:text-blue-300 text-xs font-bold uppercase shrink-0">
                        {{ user?.name?.charAt(0) ?? '?' }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ user?.name }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ user?.email }}</p>
                    </div>
                </div>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 rounded-lg transition-colors"
                >
                    <LogOut class="size-4" />
                    Đăng xuất
                </Link>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
            <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6 py-4">
                <slot name="header" />
            </header>
            <main class="flex-1 overflow-auto p-6">
                <slot />
            </main>
        </div>

        <Toaster />
        <FlashToast />
    </div>
</template>
