<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Truck,
    ShoppingBag,
    Landmark,
    ArrowRight,
    ClipboardList,
    Clock,
    CheckCircle2,
} from 'lucide-vue-next';
import DashboardShell from '@/components/dashboard/DashboardShell.vue';

const props = defineProps<{
    supplier: any;
    stats: {
        total_orders: number;
        completed_orders: number;
        pending_orders: number;
        total_revenue: number;
    };
    recentOrders: any[];
    sla: {
        on_time_rate: number;
        accuracy_rate: number;
        average_rating: number;
    };
}>();
</script>

<template>
    <Head title="Supplier Dashboard" />

    <DashboardShell :show-header="false" class="text-slate-100">
        <!-- Banner Header -->
        <div
            class="relative flex flex-col gap-6 overflow-hidden rounded-2xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/70 via-slate-50 to-emerald-100/40 p-6 text-slate-900 shadow-sm backdrop-blur-sm md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:bg-gradient-to-r dark:from-slate-900 dark:to-emerald-950/40 dark:text-slate-100 dark:shadow-xl"
        >
            <div
                class="absolute -top-16 -right-16 h-44 w-44 rounded-full bg-emerald-500/10 blur-3xl"
            ></div>
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span
                        class="rounded border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-700 uppercase dark:text-emerald-400"
                        >Portal B2B</span
                    >
                    <h1 class="text-2xl font-black text-slate-900 dark:text-slate-200">
                        Chào mừng, {{ supplier.name }}
                    </h1>
                </div>
                <p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                    Cổng kết nối chuỗi cung ứng trực tiếp với Aventura SaaS.
                    Theo dõi vận đơn, cập nhật giá bán thời gian thực.
                </p>
            </div>
            <Link
                href="/supplier/orders"
                class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-lg shadow-emerald-950/30 transition-all hover:bg-emerald-500 md:self-auto"
            >
                Xem đơn hàng hôm nay
                <ArrowRight class="h-4 w-4" />
            </Link>
        </div>

        <!-- KPIs Stats Grid -->
        <div class="dashboard-kpi-grid">
            <!-- Stat 1 -->
            <div
                class="dashboard-kpi-card flex items-center justify-between rounded-xl border border-slate-800/80 bg-slate-900/60 p-5 shadow-md backdrop-blur-sm"
            >
                <div class="space-y-1">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >Tổng số đơn PO</span
                    >
                    <p class="text-3xl font-black text-slate-100">
                        {{ stats.total_orders }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-800 bg-slate-950 p-3 text-emerald-400"
                >
                    <ClipboardList class="h-6 w-6" />
                </div>
            </div>

            <!-- Stat 2 -->
            <div
                class="dashboard-kpi-card flex items-center justify-between rounded-xl border border-slate-800/80 bg-slate-900/60 p-5 shadow-md backdrop-blur-sm"
            >
                <div class="space-y-1">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >Đơn đang xử lý</span
                    >
                    <p class="text-3xl font-black text-slate-100">
                        {{ stats.pending_orders }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-800 bg-slate-950 p-3 text-amber-400"
                >
                    <Clock class="h-6 w-6 animate-pulse" />
                </div>
            </div>

            <!-- Stat 3 -->
            <div
                class="dashboard-kpi-card flex items-center justify-between rounded-xl border border-slate-800/80 bg-slate-900/60 p-5 shadow-md backdrop-blur-sm"
            >
                <div class="space-y-1">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >Đã hoàn thành</span
                    >
                    <p class="text-3xl font-black text-slate-100">
                        {{ stats.completed_orders }}
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-800 bg-slate-950 p-3 text-blue-400"
                >
                    <CheckCircle2 class="h-6 w-6" />
                </div>
            </div>

            <!-- Stat 4 -->
            <div
                class="dashboard-kpi-card flex items-center justify-between rounded-xl border border-slate-800/80 bg-slate-900/60 p-5 shadow-md backdrop-blur-sm"
            >
                <div class="space-y-1">
                    <span
                        class="text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >Doanh thu B2B</span
                    >
                    <p class="text-2xl font-black text-emerald-400">
                        {{
                            Number(stats.total_revenue).toLocaleString('vi-VN')
                        }}đ
                    </p>
                </div>
                <div
                    class="rounded-lg border border-slate-800 bg-slate-950 p-3 text-emerald-400"
                >
                    <Landmark class="h-6 w-6" />
                </div>
            </div>
        </div>

        <!-- SLA Metrics Dashboard Section -->
        <div
            class="dashboard-card-frame space-y-4 rounded-xl border border-slate-800/80 bg-slate-900/40 p-6 backdrop-blur-sm"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-200">
                        Hiệu suất và Chỉ số SLA vận hành
                    </h3>
                    <p class="text-xs text-slate-400">
                        Các chỉ số đo lường hiệu quả giao nhận và phục vụ chuỗi
                        cung ứng.
                    </p>
                </div>
                <span
                    class="rounded border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 text-[10px] font-bold text-indigo-400 uppercase"
                    >Realtime SLA</span
                >
            </div>

            <div class="grid grid-cols-1 gap-6 text-left md:grid-cols-3">
                <!-- SLA 1: On time -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-800/50 bg-slate-950/40 p-4"
                >
                    <div class="space-y-1">
                        <span
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Tỷ lệ đúng hạn</span
                        >
                        <div class="flex items-baseline gap-2">
                            <span
                                class="font-mono text-2xl font-black text-emerald-400"
                                >{{ sla.on_time_rate }}%</span
                            >
                            <span class="text-[9px] font-bold text-emerald-500"
                                >Mục tiêu: >95%</span
                            >
                        </div>
                    </div>
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-emerald-500"
                            :style="{ width: sla.on_time_rate + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- SLA 2: Accuracy -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-800/50 bg-slate-950/40 p-4"
                >
                    <div class="space-y-1">
                        <span
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Tỷ lệ đủ & chính xác hàng</span
                        >
                        <div class="flex items-baseline gap-2">
                            <span
                                class="font-mono text-2xl font-black text-indigo-400"
                                >{{ sla.accuracy_rate }}%</span
                            >
                            <span class="text-[9px] font-bold text-indigo-500"
                                >Mục tiêu: >98%</span
                            >
                        </div>
                    </div>
                    <div
                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-indigo-500"
                            :style="{ width: sla.accuracy_rate + '%' }"
                        ></div>
                    </div>
                </div>

                <!-- SLA 3: Rating -->
                <div
                    class="flex flex-col justify-between rounded-xl border border-slate-800/50 bg-slate-950/40 p-4"
                >
                    <div class="space-y-1">
                        <span
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Đánh giá sao trung bình</span
                        >
                        <div class="flex items-baseline gap-2">
                            <span
                                class="font-mono text-2xl font-black text-amber-500"
                                >{{ sla.average_rating }} / 5.0</span
                            >
                            <span class="text-[9px] font-bold text-amber-500"
                                >Chất lượng: Xuất sắc</span
                            >
                        </div>
                    </div>
                    <div class="mt-3 flex gap-0.5">
                        <span v-for="star in 5" :key="star" class="text-sm">
                            {{
                                star <= Math.round(sla.average_rating)
                                    ? '⭐'
                                    : '☆'
                            }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Quick Links -->
        <div class="dashboard-grid">
            <!-- Recent Orders Table -->
            <div
                class="dashboard-card-frame dashboard-list-card col-span-12 border-slate-800/80 bg-slate-900/40 p-5 text-slate-100 lg:col-span-8"
            >
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-200">
                        Đơn hàng mới nhận
                    </h3>
                    <Link
                        href="/supplier/orders"
                        class="text-xs font-semibold text-emerald-400 hover:underline"
                        >Xem tất cả</Link
                    >
                </div>

                <div class="dashboard-list-card__body divide-y divide-slate-800/50">
                    <div
                        v-for="po in recentOrders"
                        :key="po.id"
                        class="flex items-center justify-between rounded-lg px-2 py-3 transition-colors hover:bg-slate-900/20"
                    >
                        <div class="space-y-0.5">
                            <span
                                class="font-mono text-sm font-bold text-emerald-400"
                                >{{ po.po_number }}</span
                            >
                            <p class="text-[10px] text-slate-500">
                                Đặt lúc: {{ po.created_at }}
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-semibold text-slate-300"
                                >{{
                                    Number(po.total_amount).toLocaleString(
                                        'vi-VN',
                                    )
                                }}đ</span
                            >
                            <span
                                class="border-slate-850 rounded border bg-slate-950 px-2 py-0.5 text-[10px] font-semibold text-slate-400"
                            >
                                {{ po.status }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="recentOrders.length === 0"
                        class="dashboard-empty-state min-h-0 py-8 text-xs text-slate-500"
                    >
                        Chưa nhận được đơn đặt hàng nào.
                    </div>
                </div>
            </div>

            <!-- Quick tools -->
            <div
                class="dashboard-card-frame dashboard-summary-card col-span-12 border-slate-800/80 bg-slate-900/40 p-5 text-slate-100 lg:col-span-4"
            >
                <h3 class="text-lg font-bold text-slate-200">
                    Phím tắt tác vụ
                </h3>

                <div class="space-y-3">
                    <Link
                        href="/supplier/catalog"
                        class="border-slate-850 group flex items-center gap-3.5 rounded-xl border bg-slate-950/60 p-3.5 transition-all hover:border-slate-700 hover:bg-slate-950"
                    >
                        <div
                            class="rounded-lg border border-emerald-900 bg-emerald-950 p-2 text-emerald-400 transition-transform group-hover:scale-105"
                        >
                            <ShoppingBag class="h-5 w-5" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-200">
                                Bảng giá & Catalog
                            </h4>
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                Niêm yết giá bán, thêm quy cách đóng gói vật tư.
                            </p>
                        </div>
                    </Link>

                    <Link
                        href="/supplier/orders"
                        class="border-slate-850 group flex items-center gap-3.5 rounded-xl border bg-slate-950/60 p-3.5 transition-all hover:border-slate-700 hover:bg-slate-950"
                    >
                        <div
                            class="rounded-lg border border-indigo-900 bg-indigo-950 p-2 text-indigo-400 transition-transform group-hover:scale-105"
                        >
                            <Truck class="h-5 w-5" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-200">
                                Xử lý vận đơn
                            </h4>
                            <p class="mt-0.5 text-[10px] text-slate-400">
                                Cập nhật lộ trình chuẩn bị hàng, vận chuyển và
                                giao hàng.
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </DashboardShell>
</template>
