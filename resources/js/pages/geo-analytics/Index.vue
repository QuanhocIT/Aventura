<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Building2, MapPin, Navigation, Route, TrendingUp } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    restaurant: { lat: number; lng: number; name: string };
    heatmap: { lat: number; lng: number; count: number; revenue: number }[];
    zoneStats: {
        zones: { zone: string; orders: number; revenue: number; avg_order: number }[];
        avg_distance: number; max_distance: number; total_deliveries: number;
    };
    topAreas: { area: string; orders: number; revenue: number }[];
    channels: { channel: string; label: string; orders: number; revenue: number }[];
    branchSuggestions: { lat: number; lng: number; reason: string; score: number }[];
    days: number;
}>();

const totalDeliveryRevenue = props.zoneStats.zones.reduce((s, z) => s + z.revenue, 0);
const totalOrders = props.channels.reduce((s, c) => s + c.orders, 0);
const maxZoneOrders = Math.max(...props.zoneStats.zones.map(z => z.orders), 1);
</script>

<template>
    <Head title="Phân tích địa lý" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto">
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-50 dark:bg-teal-950/60 text-teal-600">
                <MapPin class="size-6" />
            </div>
            <div>
                <h1 class="text-2xl font-bold">Phân Tích Địa Lý & Vùng Phục Vụ</h1>
                <p class="text-sm text-muted-foreground">Heatmap đơn hàng, vùng giao hàng, phân tích kênh bán, gợi ý mở chi nhánh ({{ days }} ngày).</p>
            </div>
        </div>

        <!-- KPI -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Tổng đơn giao hàng</p><p class="text-2xl font-bold">{{ zoneStats.total_deliveries }}</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Khoảng cách TB</p><p class="text-2xl font-bold text-teal-600">{{ zoneStats.avg_distance }} km</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Xa nhất</p><p class="text-2xl font-bold">{{ zoneStats.max_distance }} km</p></CardContent></Card>
            <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Doanh thu giao hàng</p><p class="text-2xl font-bold text-green-600">{{ totalDeliveryRevenue.toLocaleString() }}đ</p></CardContent></Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Delivery Zone Distribution -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold flex items-center gap-2"><Navigation class="size-4" /> Phân bố vùng giao hàng</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="zone in zoneStats.zones" :key="zone.zone" class="space-y-1">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ zone.zone }}</span>
                            <span class="text-xs text-muted-foreground">{{ zone.orders }} đơn · {{ zone.revenue.toLocaleString() }}đ</span>
                        </div>
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div class="h-full bg-teal-500 rounded-full" :style="{ width: (zone.orders / maxZoneOrders * 100) + '%' }"></div>
                        </div>
                        <p class="text-xs text-muted-foreground">TB/đơn: {{ zone.avg_order.toLocaleString() }}đ</p>
                    </div>
                    <p v-if="!zoneStats.zones.length" class="text-sm text-muted-foreground text-center py-4">Chưa có dữ liệu giao hàng.</p>
                </CardContent>
            </Card>

            <!-- Top Areas -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold flex items-center gap-2"><MapPin class="size-4" /> Top khu vực đặt hàng</CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div v-for="(area, idx) in topAreas" :key="idx" class="flex items-center justify-between text-sm">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground w-4">{{ idx + 1 }}</span>
                            <span class="truncate max-w-40">{{ area.area }}</span>
                        </div>
                        <div class="text-right">
                            <span class="font-bold">{{ area.orders }}</span>
                            <span class="text-xs text-muted-foreground ml-1">đơn</span>
                        </div>
                    </div>
                    <p v-if="!topAreas.length" class="text-sm text-muted-foreground text-center py-4">Chưa có dữ liệu.</p>
                </CardContent>
            </Card>

            <!-- Channel Breakdown -->
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm font-semibold flex items-center gap-2"><Route class="size-4" /> Phân tích kênh bán hàng</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="ch in channels" :key="ch.channel" class="flex items-center justify-between">
                        <div>
                            <span class="text-sm font-medium">{{ ch.label }}</span>
                            <span class="text-xs text-muted-foreground ml-1">({{ totalOrders > 0 ? Math.round(ch.orders / totalOrders * 100) : 0 }}%)</span>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold">{{ ch.orders }} đơn</p>
                            <p class="text-xs text-muted-foreground">{{ ch.revenue.toLocaleString() }}đ</p>
                        </div>
                    </div>
                    <p v-if="!channels.length" class="text-sm text-muted-foreground text-center py-4">Chưa có dữ liệu.</p>
                </CardContent>
            </Card>
        </div>

        <!-- Heatmap data (table view since no map library) -->
        <Card>
            <CardHeader>
                <CardTitle class="text-base flex items-center gap-2"><TrendingUp class="size-5" /> Heatmap điểm nóng giao hàng</CardTitle>
                <CardDescription>Top {{ heatmap.length }} khu vực có mật độ đơn hàng cao nhất. Tọa độ rounded 3 decimals (~111m).</CardDescription>
            </CardHeader>
            <CardContent class="p-0">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="px-4 py-2">#</th>
                            <th class="px-4 py-2">Tọa độ</th>
                            <th class="px-4 py-2 text-right">Số đơn</th>
                            <th class="px-4 py-2 text-right">Doanh thu</th>
                            <th class="px-4 py-2">Mật độ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(point, idx) in heatmap.slice(0, 20)" :key="idx" class="border-b last:border-0">
                            <td class="px-4 py-2 text-muted-foreground">{{ idx + 1 }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ point.lat }}, {{ point.lng }}</td>
                            <td class="px-4 py-2 text-right font-bold">{{ point.count }}</td>
                            <td class="px-4 py-2 text-right text-xs">{{ point.revenue.toLocaleString() }}đ</td>
                            <td class="px-4 py-2">
                                <div class="h-2 w-16 rounded-full bg-muted overflow-hidden">
                                    <div class="h-full bg-red-500 rounded-full" :style="{ width: Math.min(100, (point.count / Math.max(1, heatmap[0]?.count ?? 1)) * 100) + '%' }"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!heatmap.length" class="text-center text-muted-foreground py-12">Chưa có dữ liệu tọa độ giao hàng.</p>
            </CardContent>
        </Card>

        <!-- Branch Suggestions -->
        <Card v-if="branchSuggestions.length">
            <CardHeader>
                <CardTitle class="text-base flex items-center gap-2 text-violet-600">
                    <Building2 class="size-5" /> AI Gợi ý vị trí mở chi nhánh mới
                </CardTitle>
                <CardDescription>Dựa trên mật độ đơn hàng xa nhà hàng chính (>5km) trong 90 ngày.</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-for="(s, idx) in branchSuggestions" :key="idx"
                    class="rounded-xl border p-4 space-y-1 hover:bg-violet-50/50 dark:hover:bg-violet-950/20 transition-colors">
                    <div class="flex items-center gap-2">
                        <Badge class="bg-violet-100 text-violet-800 text-xs">Vị trí {{ idx + 1 }}</Badge>
                        <span class="text-xs font-mono text-muted-foreground">{{ s.lat }}, {{ s.lng }}</span>
                    </div>
                    <p class="text-sm">{{ s.reason }}</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
