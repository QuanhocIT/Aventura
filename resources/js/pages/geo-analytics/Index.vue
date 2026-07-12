<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    MapPin,
    Navigation,
    Route,
    TrendingUp,
    ShoppingCart,
    Banknote,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurant: { lat: number; lng: number; name: string };
    heatmap: { lat: number; lng: number; count: number; revenue: number }[];
    zoneStats: {
        zones: {
            zone: string;
            orders: number;
            revenue: number;
            avg_order: number;
        }[];
        avg_distance: number;
        max_distance: number;
        total_deliveries: number;
    };
    topAreas: { area: string; orders: number; revenue: number }[];
    channels: {
        channel: string;
        label: string;
        orders: number;
        revenue: number;
    }[];
    branchSuggestions: {
        lat: number;
        lng: number;
        reason: string;
        score: number;
    }[];
    days: number;
}>();

const totalDeliveryRevenue = props.zoneStats.zones.reduce(
    (s, z) => s + z.revenue,
    0,
);
const totalOrders = computed(() =>
    props.channels.reduce((s, c) => s + c.orders, 0),
);
const maxZoneOrders = Math.max(
    ...props.zoneStats.zones.map((z) => z.orders),
    1,
);

const mapContainer = ref<HTMLElement | null>(null);
let map: any = null;
let L: any = null;

onMounted(async () => {
    if (!mapContainer.value) {
        return;
    }

    try {
        L = (await import('leaflet')).default;
        await import('leaflet/dist/leaflet.css');

        // Fix default Leaflet icon paths
        const DefaultIcon = L.icon({
            iconUrl:
                'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
            shadowUrl:
                'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41],
        });
        L.Marker.prototype.options.icon = DefaultIcon;

        // Initialize map centered at restaurant
        map = L.map(mapContainer.value, {
            zoomControl: true,
            attributionControl: false,
        }).setView([props.restaurant.lat, props.restaurant.lng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap',
        }).addTo(map);

        L.control.attribution({ prefix: false }).addTo(map);

        // 1. Restaurant Marker
        L.marker([props.restaurant.lat, props.restaurant.lng])
            .addTo(map)
            .bindPopup(
                `<strong>${props.restaurant.name}</strong><br/>📍 Cửa hàng chính`,
            )
            .openPopup();

        // 2. Heatmap points (Circles)
        const maxCount = Math.max(...props.heatmap.map((h) => h.count), 1);
        props.heatmap.forEach((point) => {
            const ratio = point.count / maxCount;
            const radius = 150 + ratio * 250; // Radius in meters
            L.circle([point.lat, point.lng], {
                color: '#ef4444',
                fillColor: '#f87171',
                fillOpacity: 0.15 + ratio * 0.35,
                radius: radius,
                stroke: false,
            })
                .addTo(map)
                .bindPopup(
                    `<strong>Vùng giao hàng</strong><br/>Số đơn: ${point.count}<br/>Doanh thu: ${point.revenue.toLocaleString()}đ`,
                );
        });

        // 3. AI Branch Suggestions
        props.branchSuggestions.forEach((suggestion, idx) => {
            const suggestionIcon = L.divIcon({
                className: 'custom-branch-icon',
                html: `<div class="flex items-center justify-center w-8 h-8 rounded-full bg-violet-600 border-2 border-white text-white font-bold text-xs shadow-lg animate-pulse">${idx + 1}</div>`,
                iconSize: [32, 32],
                iconAnchor: [16, 16],
            });

            L.marker([suggestion.lat, suggestion.lng], {
                icon: suggestionIcon,
            }).addTo(map).bindPopup(`
                    <div class="p-1 min-w-[200px]">
                        <span class="inline-block bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300 font-bold text-[10px] px-2 py-0.5 rounded-full mb-1">
                            Gợi ý chi nhánh ${idx + 1} (Điểm: ${suggestion.score}/100)
                        </span>
                        <p class="text-xs text-foreground mt-1 leading-relaxed">${suggestion.reason}</p>
                        <p class="text-[10px] text-muted-foreground font-mono mt-1">Tọa độ: ${suggestion.lat}, ${suggestion.lng}</p>
                    </div>
                `);
        });
    } catch (err) {
        console.error('Lỗi khởi tạo bản đồ Leaflet:', err);
    }
});

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <Head title="Phân tích địa lý" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 lg:p-6">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-teal-500/10 text-teal-600 dark:text-teal-400"
                >
                    <MapPin class="size-6 text-teal-600 dark:text-teal-400" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Phân Tích Địa Lý & Vùng Phục Vụ
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Heatmap đơn hàng, vùng giao hàng, phân tích kênh bán,
                        gợi ý mở chi nhánh ({{ days }} ngày).
                    </p>
                </div>
            </div>
        </div>

        <!-- Interactive Map Container -->
        <Card class="overflow-hidden border border-border bg-card shadow-sm">
            <CardContent class="relative p-0">
                <div class="px-5 pt-5 pb-3">
                    <div class="mb-1 flex items-center gap-2">
                        <Navigation class="size-4 text-teal-500" />
                        <p class="text-sm font-semibold">
                            Bản đồ nhiệt & Địa điểm đề xuất mở rộng
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Xem mật độ đơn hàng thực tế (Vòng tròn đỏ) và các khu
                        vực AI gợi ý mở chi nhánh (Marker số màu tím).
                    </p>
                </div>
                <div
                    ref="mapContainer"
                    class="z-10 h-[450px] w-full border-t border-border"
                ></div>
            </CardContent>
        </Card>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <!-- 1. Tổng đơn giao hàng -->
            <Card>
                <CardContent class="px-4 pt-4 pb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Tổng đơn</span
                        >
                        <ShoppingCart class="size-4 text-sky-500" />
                    </div>
                    <p class="text-xl font-bold text-foreground">
                        {{ zoneStats.total_deliveries }}
                    </p>
                    <p class="mt-1.5 text-[10px] text-muted-foreground">
                        Tổng đơn hàng giao tận nơi
                    </p>
                </CardContent>
            </Card>

            <!-- 2. Khoảng cách TB -->
            <Card>
                <CardContent class="px-4 pt-4 pb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Cự ly trung bình</span
                        >
                        <Route class="size-4 text-teal-500" />
                    </div>
                    <p
                        class="text-xl font-bold text-teal-600 dark:text-teal-400"
                    >
                        {{ zoneStats.avg_distance }} km
                    </p>
                    <p class="mt-1.5 text-[10px] text-muted-foreground">
                        Khoảng cách giao hàng TB
                    </p>
                </CardContent>
            </Card>

            <!-- 3. Xa nhất -->
            <Card>
                <CardContent class="px-4 pt-4 pb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Cự ly xa nhất</span
                        >
                        <MapPin class="size-4 text-amber-500" />
                    </div>
                    <p class="text-xl font-bold text-foreground">
                        {{ zoneStats.max_distance }} km
                    </p>
                    <p class="mt-1.5 text-[10px] text-muted-foreground">
                        Khoảng cách giao xa nhất
                    </p>
                </CardContent>
            </Card>

            <!-- 4. Doanh thu giao hàng -->
            <Card>
                <CardContent class="px-4 pt-4 pb-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span
                            class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                            >Doanh thu giao hàng</span
                        >
                        <Banknote class="size-4 text-emerald-500" />
                    </div>
                    <p
                        class="text-xl font-bold text-emerald-600 dark:text-emerald-400"
                    >
                        {{ totalDeliveryRevenue.toLocaleString() }}đ
                    </p>
                    <p class="mt-1.5 text-[10px] text-muted-foreground">
                        Tổng tiền thu từ giao hàng
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Delivery Zone Distribution -->
            <Card>
                <CardContent class="space-y-3 pt-5">
                    <p
                        class="mb-4 flex items-center gap-2 text-sm font-semibold"
                    >
                        <Navigation class="size-4 text-teal-500" />
                        Phân bố vùng giao hàng
                    </p>
                    <div
                        v-for="zone in zoneStats.zones"
                        :key="zone.zone"
                        class="space-y-1"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ zone.zone }}</span>
                            <span class="text-xs text-muted-foreground"
                                >{{ zone.orders }} đơn ·
                                {{ zone.revenue.toLocaleString() }}đ</span
                            >
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-teal-500"
                                :style="{
                                    width:
                                        (zone.orders / maxZoneOrders) * 100 +
                                        '%',
                                }"
                            ></div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            TB/đơn: {{ zone.avg_order.toLocaleString() }}đ
                        </p>
                    </div>
                    <p
                        v-if="!zoneStats.zones.length"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Chưa có dữ liệu giao hàng.
                    </p>
                </CardContent>
            </Card>

            <!-- Top Areas -->
            <Card>
                <CardContent class="space-y-2 pt-5">
                    <p
                        class="mb-4 flex items-center gap-2 text-sm font-semibold"
                    >
                        <MapPin class="size-4 text-teal-500" />
                        Top khu vực đặt hàng
                    </p>
                    <div
                        v-for="(area, idx) in topAreas"
                        :key="idx"
                        class="flex items-center justify-between text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <span class="w-4 text-xs text-muted-foreground">{{
                                idx + 1
                            }}</span>
                            <span class="max-w-40 truncate">{{
                                area.area
                            }}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-bold">{{ area.orders }}</span>
                            <span class="ml-1 text-xs text-muted-foreground"
                                >đơn</span
                            >
                        </div>
                    </div>
                    <p
                        v-if="!topAreas.length"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Chưa có dữ liệu.
                    </p>
                </CardContent>
            </Card>

            <!-- Channel Breakdown -->
            <Card>
                <CardContent class="space-y-3 pt-5">
                    <p
                        class="mb-4 flex items-center gap-2 text-sm font-semibold"
                    >
                        <Route class="size-4 text-teal-500" />
                        Phân tích kênh bán hàng
                    </p>
                    <div
                        v-for="ch in channels"
                        :key="ch.channel"
                        class="flex items-center justify-between"
                    >
                        <div>
                            <span class="text-sm font-medium">{{
                                ch.label
                            }}</span>
                            <span class="ml-1 text-xs text-muted-foreground"
                                >({{
                                    totalOrders > 0
                                        ? Math.round(
                                              (ch.orders / totalOrders) * 100,
                                          )
                                        : 0
                                }}%)</span
                            >
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold">{{ ch.orders }} đơn</p>
                            <p class="text-xs text-muted-foreground">
                                {{ ch.revenue.toLocaleString() }}đ
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="!channels.length"
                        class="py-4 text-center text-sm text-muted-foreground"
                    >
                        Chưa có dữ liệu.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Heatmap data (table view since no map library) -->
        <Card>
            <CardContent class="p-0 pt-5">
                <div class="px-5 pb-3">
                    <div class="mb-1 flex items-center gap-2">
                        <TrendingUp class="size-4 text-teal-500" />
                        <p class="text-sm font-semibold">
                            Heatmap điểm nóng giao hàng
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Top {{ heatmap.length }} khu vực có mật độ đơn hàng cao
                        nhất. Tọa độ rounded 3 decimals (~111m).
                    </p>
                </div>
                <table class="w-full border-t border-border text-sm">
                    <thead class="bg-muted/50">
                        <tr
                            class="border-b text-left text-xs text-muted-foreground"
                        >
                            <th class="px-4 py-2">#</th>
                            <th class="px-4 py-2">Tọa độ</th>
                            <th class="px-4 py-2 text-right">Số đơn</th>
                            <th class="px-4 py-2 text-right">Doanh thu</th>
                            <th class="px-4 py-2">Mật độ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(point, idx) in heatmap.slice(0, 20)"
                            :key="idx"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-2 text-muted-foreground">
                                {{ idx + 1 }}
                            </td>
                            <td class="px-4 py-2 font-mono text-xs">
                                {{ point.lat }}, {{ point.lng }}
                            </td>
                            <td class="px-4 py-2 text-right font-bold">
                                {{ point.count }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs">
                                {{ point.revenue.toLocaleString() }}đ
                            </td>
                            <td class="px-4 py-2">
                                <div
                                    class="h-2 w-16 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-red-500"
                                        :style="{
                                            width:
                                                Math.min(
                                                    100,
                                                    (point.count /
                                                        Math.max(
                                                            1,
                                                            heatmap[0]?.count ??
                                                                1,
                                                        )) *
                                                        100,
                                                ) + '%',
                                        }"
                                    ></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!heatmap.length"
                    class="py-12 text-center text-muted-foreground"
                >
                    Chưa có dữ liệu tọa độ giao hàng.
                </p>
            </CardContent>
        </Card>

        <!-- Branch Suggestions -->
        <Card v-if="branchSuggestions.length">
            <CardContent class="space-y-4 pt-5">
                <div class="pb-1">
                    <div class="mb-1 flex items-center gap-2">
                        <Building2 class="size-4 text-violet-500" />
                        <p class="text-sm font-semibold text-violet-600">
                            AI Gợi ý vị trí mở chi nhánh mới
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Dựa trên mật độ đơn hàng xa nhà hàng chính (>5km) trong
                        90 ngày.
                    </p>
                </div>
                <div class="space-y-4 border-t border-border pt-4">
                    <div
                        v-for="(s, idx) in branchSuggestions"
                        :key="idx"
                        class="space-y-1 rounded-xl border p-4 transition-colors hover:bg-violet-50/50 dark:hover:bg-violet-950/20"
                    >
                        <div class="flex items-center gap-2">
                            <Badge class="bg-violet-100 text-xs text-violet-800"
                                >Vị trí {{ idx + 1 }}</Badge
                            >
                            <span
                                class="font-mono text-xs text-muted-foreground"
                                >{{ s.lat }}, {{ s.lng }}</span
                            >
                        </div>
                        <p class="text-sm">{{ s.reason }}</p>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
