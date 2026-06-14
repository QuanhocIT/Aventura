<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { 
    Truck, ShoppingBag, Landmark, ArrowRight,
    ClipboardList, Clock, CheckCircle2, ShieldAlert
} from 'lucide-vue-next';

const props = defineProps<{
    supplier: any;
    stats: {
        total_orders: number;
        completed_orders: number;
        pending_orders: number;
        total_revenue: number;
    };
    recentOrders: any[];
}>();
</script>

<template>
    <Head title="Supplier Dashboard" />

    <div class="px-6 py-8 max-w-7xl mx-auto space-y-8 text-slate-100">
        <!-- Banner Header -->
        <div class="bg-gradient-to-r from-slate-900 to-emerald-950/40 border border-slate-800 p-6 rounded-2xl flex flex-col md:flex-row md:items-center md:justify-between gap-6 shadow-xl backdrop-blur-sm relative overflow-hidden">
            <div class="absolute -right-16 -top-16 w-44 h-44 bg-emerald-500/10 rounded-full blur-3xl"></div>
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-[10px] uppercase font-bold rounded">Portal B2B</span>
                    <h1 class="text-2xl font-black text-slate-200">Chào mừng, {{ supplier.name }}</h1>
                </div>
                <p class="text-sm text-slate-400 leading-relaxed">Cổng kết nối chuỗi cung ứng trực tiếp với Aventura SaaS. Theo dõi vận đơn, cập nhật giá bán thời gian thực.</p>
            </div>
            <Link 
                href="/supplier/orders" 
                class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-semibold shadow-lg shadow-emerald-950/30 transition-all shrink-0 self-start md:self-auto"
            >
                Xem đơn hàng hôm nay
                <ArrowRight class="w-4 h-4" />
            </Link>
        </div>

        <!-- KPIs Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Stat 1 -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-5 flex items-center justify-between shadow-md backdrop-blur-sm">
                <div class="space-y-1">
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Tổng số đơn PO</span>
                    <p class="text-3xl font-black text-slate-100">{{ stats.total_orders }}</p>
                </div>
                <div class="p-3 bg-slate-950 border border-slate-800 rounded-lg text-emerald-400">
                    <ClipboardList class="w-6 h-6" />
                </div>
            </div>

            <!-- Stat 2 -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-5 flex items-center justify-between shadow-md backdrop-blur-sm">
                <div class="space-y-1">
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Đơn đang xử lý</span>
                    <p class="text-3xl font-black text-slate-100">{{ stats.pending_orders }}</p>
                </div>
                <div class="p-3 bg-slate-950 border border-slate-800 rounded-lg text-amber-400">
                    <Clock class="w-6 h-6 animate-pulse" />
                </div>
            </div>

            <!-- Stat 3 -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-5 flex items-center justify-between shadow-md backdrop-blur-sm">
                <div class="space-y-1">
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Đã hoàn thành</span>
                    <p class="text-3xl font-black text-slate-100">{{ stats.completed_orders }}</p>
                </div>
                <div class="p-3 bg-slate-950 border border-slate-800 rounded-lg text-blue-400">
                    <CheckCircle2 class="w-6 h-6" />
                </div>
            </div>

            <!-- Stat 4 -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-5 flex items-center justify-between shadow-md backdrop-blur-sm">
                <div class="space-y-1">
                    <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Doanh thu B2B</span>
                    <p class="text-2xl font-black text-emerald-400">{{ Number(stats.total_revenue).toLocaleString('vi-VN') }}đ</p>
                </div>
                <div class="p-3 bg-slate-950 border border-slate-800 rounded-lg text-emerald-400">
                    <Landmark class="w-6 h-6" />
                </div>
            </div>
        </div>

        <!-- Recent Activity & Quick Links -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Orders Table -->
            <div class="lg:col-span-2 bg-slate-900/40 border border-slate-800/80 rounded-xl p-5 backdrop-blur-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-200">Đơn hàng mới nhận</h3>
                    <Link href="/supplier/orders" class="text-xs font-semibold text-emerald-400 hover:underline">Xem tất cả</Link>
                </div>

                <div class="divide-y divide-slate-800/50">
                    <div 
                        v-for="po in recentOrders" 
                        :key="po.id" 
                        class="py-3 flex items-center justify-between hover:bg-slate-900/20 px-2 rounded-lg transition-colors"
                    >
                        <div class="space-y-0.5">
                            <span class="text-sm font-mono font-bold text-emerald-400">{{ po.po_number }}</span>
                            <p class="text-[10px] text-slate-500">Đặt lúc: {{ po.created_at }}</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-semibold text-slate-300">{{ Number(po.total_amount).toLocaleString('vi-VN') }}đ</span>
                            <span class="text-[10px] px-2 py-0.5 rounded font-semibold bg-slate-950 border border-slate-850 text-slate-400">
                                {{ po.status }}
                            </span>
                        </div>
                    </div>

                    <div v-if="recentOrders.length === 0" class="py-8 text-center text-slate-500 text-xs">
                        Chưa nhận được đơn đặt hàng nào.
                    </div>
                </div>
            </div>

            <!-- Quick tools -->
            <div class="lg:col-span-1 bg-slate-900/40 border border-slate-800/80 rounded-xl p-5 backdrop-blur-sm space-y-5">
                <h3 class="text-lg font-bold text-slate-200">Phím tắt tác vụ</h3>
                
                <div class="space-y-3">
                    <Link 
                        href="/supplier/catalog" 
                        class="flex items-center gap-3.5 p-3.5 bg-slate-950/60 border border-slate-850 hover:border-slate-700 hover:bg-slate-950 rounded-xl transition-all group"
                    >
                        <div class="p-2 bg-emerald-950 text-emerald-400 border border-emerald-900 rounded-lg group-hover:scale-105 transition-transform">
                            <ShoppingBag class="w-5 h-5" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-200">Bảng giá & Catalog</h4>
                            <p class="text-[10px] text-slate-400 mt-0.5">Niêm yết giá bán, thêm quy cách đóng gói vật tư.</p>
                        </div>
                    </Link>

                    <Link 
                        href="/supplier/orders" 
                        class="flex items-center gap-3.5 p-3.5 bg-slate-950/60 border border-slate-850 hover:border-slate-700 hover:bg-slate-950 rounded-xl transition-all group"
                    >
                        <div class="p-2 bg-indigo-950 text-indigo-400 border border-indigo-900 rounded-lg group-hover:scale-105 transition-transform">
                            <Truck class="w-5 h-5" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-200">Xử lý vận đơn</h4>
                            <p class="text-[10px] text-slate-400 mt-0.5">Cập nhật lộ trình chuẩn bị hàng, vận chuyển và giao hàng.</p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
