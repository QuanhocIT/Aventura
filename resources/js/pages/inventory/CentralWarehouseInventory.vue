<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    CalendarClock,
    ChevronDown,
    ChevronRight,
    ClipboardList,
    PackageCheck,
    PackageSearch,
    Search,
    ShieldAlert,
    Truck,
    Warehouse,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Batch = {
    id: number;
    batch_number: string;
    quantity_remaining: number;
    unit_cost: number;
    purchased_at: string | null;
    expiry_date: string | null;
    days_remaining: number | null;
    status: string;
    is_expired: boolean;
    is_expiring_soon: boolean;
};

type StockItem = {
    id: number;
    name: string;
    sku: string | null;
    category_name: string | null;
    storage_type_label: string;
    unit_symbol: string;
    on_hand: number;
    theoretical: number;
    variance: number;
    reserved: number;
    available: number;
    min_stock_level: number;
    reorder_level: number;
    average_cost: number;
    stock_value: number;
    status: 'out' | 'low' | 'expiring' | 'locked' | 'normal';
    last_counted_at: string | null;
    batches: Batch[];
};

const props = defineProps<{
    centralBranch: { id: number; name: string } | null;
    centralStockItems: StockItem[];
    inventorySummary: Record<string, number>;
}>();

const search = ref('');
const statusFilter = ref('all');
const categoryFilter = ref('all');
const expandedId = ref<number | null>(null);

const categories = computed(() => [
    'all',
    ...Array.from(
        new Set(
            props.centralStockItems.map((item) => item.category_name || 'Khác'),
        ),
    ).sort((a, b) => a.localeCompare(b, 'vi')),
]);

const filteredItems = computed(() => {
    const query = search.value.trim().toLowerCase();

    return [...props.centralStockItems]
        .filter((item) => {
            const matchesSearch =
                !query ||
                [item.name, item.sku, item.category_name]
                    .filter(Boolean)
                    .join(' ')
                    .toLowerCase()
                    .includes(query);
            const matchesStatus =
                statusFilter.value === 'all' ||
                item.status === statusFilter.value;
            const matchesCategory =
                categoryFilter.value === 'all' ||
                (item.category_name || 'Khác') === categoryFilter.value;

            return matchesSearch && matchesStatus && matchesCategory;
        })
        .sort((a, b) => {
            const priority: Record<string, number> = {
                out: 0,
                low: 1,
                expiring: 2,
                locked: 3,
                normal: 4,
            };

            return (
                priority[a.status] - priority[b.status] ||
                a.name.localeCompare(b.name, 'vi')
            );
        });
});

const formatQuantity = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
        Number(value || 0),
    );

const formatCurrency = (value: number | string | null | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(Number(value || 0));

const statusMeta = (status: StockItem['status']) =>
    ({
        out: {
            label: 'Hết hàng',
            class: 'bg-rose-500/10 text-rose-300 border-rose-500/25',
        },
        low: {
            label: 'Dưới định mức',
            class: 'bg-amber-500/10 text-amber-300 border-amber-500/25',
        },
        expiring: {
            label: 'Sắp hết HSD',
            class: 'bg-orange-500/10 text-orange-300 border-orange-500/25',
        },
        locked: {
            label: 'Có lô cần xử lý',
            class: 'bg-slate-500/10 text-slate-300 border-slate-500/25',
        },
        normal: {
            label: 'Ổn định',
            class: 'bg-emerald-500/10 text-emerald-300 border-emerald-500/25',
        },
    })[status];

const toggleExpanded = (id: number) => {
    expandedId.value = expandedId.value === id ? null : id;
};
</script>

<template>
    <Head title="Tồn kho Kho Tổng" />

    <div class="mx-auto w-full max-w-[1500px] space-y-5 p-4 sm:p-6">
        <section
            class="rounded-3xl border border-indigo-500/20 bg-gradient-to-br from-slate-950 via-indigo-950/90 to-slate-900 p-6 text-white shadow-xl sm:p-8"
        >
            <div
                class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
            >
                <div>
                    <Link
                        href="/inventory/central-warehouse"
                        class="mb-3 inline-flex items-center gap-1 text-xs text-indigo-200 hover:text-white"
                        >← Tổng quan Kho Tổng</Link
                    >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/20 text-indigo-200"
                        >
                            <Warehouse class="h-6 w-6" />
                        </div>
                        <div>
                            <h1
                                class="text-2xl font-bold tracking-tight sm:text-3xl"
                            >
                                Tồn kho Kho Tổng
                            </h1>
                            <p class="mt-1 text-sm text-indigo-100/75">
                                Theo dõi tồn thực tế, tồn khả dụng và lô hàng
                                tại kho nguồn.
                            </p>
                        </div>
                    </div>
                </div>
                <div
                    class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm backdrop-blur-sm"
                >
                    <p
                        class="text-[10px] font-semibold tracking-[0.16em] text-indigo-200/70 uppercase"
                    >
                        Phạm vi dữ liệu
                    </p>
                    <p class="mt-1 font-semibold text-white">
                        {{ centralBranch?.name || 'Kho Tổng chưa thiết lập' }}
                    </p>
                    <p class="mt-1 text-xs text-indigo-100/60">
                        Không phụ thuộc chi nhánh đang chọn trên header
                    </p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <Card class="border-indigo-500/20 bg-indigo-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-indigo-300 uppercase">
                        Mặt hàng
                    </p>
                    <p class="mt-2 text-2xl font-bold text-indigo-100">
                        {{ inventorySummary.ingredient_count || 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Trong catalog Kho Tổng
                    </p></CardContent
                ></Card
            >
            <Card class="border-sky-500/20 bg-sky-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-sky-300 uppercase">
                        Tồn thực tế
                    </p>
                    <p class="mt-2 text-2xl font-bold text-sky-100">
                        {{ formatQuantity(inventorySummary.on_hand_quantity) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Đơn vị hàng hóa
                    </p></CardContent
                ></Card
            >
            <Card class="border-emerald-500/20 bg-emerald-950/10"
                ><CardContent class="p-4"
                    ><p
                        class="text-[11px] font-bold text-emerald-300 uppercase"
                    >
                        Tồn khả dụng
                    </p>
                    <p class="mt-2 text-2xl font-bold text-emerald-100">
                        {{
                            formatQuantity(inventorySummary.available_quantity)
                        }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Sau khi trừ giữ chỗ
                    </p></CardContent
                ></Card
            >
            <Card class="border-violet-500/20 bg-violet-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-violet-300 uppercase">
                        Giá trị tồn
                    </p>
                    <p class="mt-2 text-lg font-bold text-violet-100">
                        {{ formatCurrency(inventorySummary.on_hand_value) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Theo giá vốn bình quân
                    </p></CardContent
                ></Card
            >
            <Card class="border-amber-500/20 bg-amber-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-amber-300 uppercase">
                        Cần bổ sung
                    </p>
                    <p class="mt-2 text-2xl font-bold text-amber-100">
                        {{ inventorySummary.low_stock_count || 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Hết hàng hoặc dưới mức tối thiểu
                    </p></CardContent
                ></Card
            >
            <Card class="border-orange-500/20 bg-orange-950/10"
                ><CardContent class="p-4"
                    ><p class="text-[11px] font-bold text-orange-300 uppercase">
                        Lô sắp hết hạn
                    </p>
                    <p class="mt-2 text-2xl font-bold text-orange-100">
                        {{ inventorySummary.expiring_soon_count || 0 }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Trong 3 ngày tới
                    </p></CardContent
                ></Card
            >
        </section>

        <Card class="border-border shadow-sm">
            <CardHeader class="border-b border-border bg-muted/20 py-4">
                <div
                    class="flex flex-col justify-between gap-3 lg:flex-row lg:items-center"
                >
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base"
                            ><PackageSearch class="h-5 w-5 text-indigo-300" />
                            Danh sách tồn Kho Tổng</CardTitle
                        >
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ filteredItems.length }}/{{
                                centralStockItems.length
                            }}
                            mặt hàng · Tồn khả dụng luôn đã trừ các đơn cấp phát
                            đang giữ chỗ.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link href="/inventory/central-warehouse/requests"
                            ><Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5 text-xs"
                                ><Truck class="h-3.5 w-3.5" /> Đơn cấp
                                phát</Button
                            ></Link
                        >
                        <Link href="/inventory/central-warehouse/receiving"
                            ><Button
                                size="sm"
                                class="gap-1.5 bg-orange-600 text-xs text-white hover:bg-orange-700"
                                ><PackageCheck class="h-3.5 w-3.5" /> Nhận hàng
                                & GRN</Button
                            ></Link
                        >
                    </div>
                </div>
            </CardHeader>
            <CardContent class="space-y-4 p-4">
                <div
                    class="grid gap-3 md:grid-cols-[minmax(0,1fr)_180px_180px]"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground"
                        /><Input
                            v-model="search"
                            placeholder="Tìm tên, SKU hoặc nhóm nguyên liệu..."
                            class="h-9 pl-9 text-xs"
                        />
                    </div>
                    <select
                        v-model="statusFilter"
                        class="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground"
                    >
                        <option value="all">Tất cả trạng thái</option>
                        <option value="out">Hết hàng</option>
                        <option value="low">Dưới định mức</option>
                        <option value="expiring">Sắp hết HSD</option>
                        <option value="locked">Có lô cần xử lý</option>
                        <option value="normal">Ổn định</option>
                    </select>
                    <select
                        v-model="categoryFilter"
                        class="h-9 rounded-md border border-input bg-background px-3 text-xs text-foreground"
                    >
                        <option
                            v-for="category in categories"
                            :key="category"
                            :value="category"
                        >
                            {{ category === 'all' ? 'Tất cả nhóm' : category }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="!centralBranch"
                    class="rounded-xl border border-dashed border-rose-500/30 bg-rose-500/5 p-8 text-center text-sm text-rose-300"
                >
                    Chưa thiết lập Kho Tổng cho nhà hàng.
                </div>
                <div
                    v-else-if="filteredItems.length === 0"
                    class="rounded-xl border border-dashed border-border bg-muted/20 p-10 text-center text-sm text-muted-foreground"
                >
                    Không có mặt hàng phù hợp với bộ lọc hiện tại.
                </div>
                <div
                    v-else
                    class="overflow-x-auto rounded-xl border border-border"
                >
                    <table class="w-full min-w-[980px] text-left text-xs">
                        <thead
                            class="border-b border-border bg-muted/40 text-muted-foreground"
                        >
                            <tr>
                                <th class="w-8 p-3"></th>
                                <th class="p-3">Nguyên liệu</th>
                                <th class="p-3">Trạng thái</th>
                                <th class="p-3 text-right">Tồn thực</th>
                                <th class="p-3 text-right">Giữ chỗ</th>
                                <th class="p-3 text-right">Khả dụng</th>
                                <th class="p-3 text-right">Tối thiểu</th>
                                <th class="p-3 text-right">Giá trị tồn</th>
                                <th class="p-3">Lô gần nhất</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <template
                                v-for="item in filteredItems"
                                :key="item.id"
                            >
                                <tr
                                    class="cursor-pointer transition hover:bg-muted/20"
                                    @click="toggleExpanded(item.id)"
                                >
                                    <td class="p-3 text-muted-foreground">
                                        <ChevronDown
                                            v-if="expandedId === item.id"
                                            class="h-4 w-4"
                                        /><ChevronRight
                                            v-else
                                            class="h-4 w-4"
                                        />
                                    </td>
                                    <td class="p-3">
                                        <p class="font-bold text-foreground">
                                            {{ item.name }}
                                        </p>
                                        <p
                                            class="mt-1 text-[10px] text-muted-foreground"
                                        >
                                            {{ item.sku || 'Chưa có SKU' }} ·
                                            {{ item.category_name || 'Khác' }} ·
                                            {{ item.storage_type_label }}
                                        </p>
                                    </td>
                                    <td class="p-3">
                                        <span
                                            class="rounded-full border px-2 py-1 text-[10px] font-semibold"
                                            :class="
                                                statusMeta(item.status).class
                                            "
                                            >{{
                                                statusMeta(item.status).label
                                            }}</span
                                        >
                                    </td>
                                    <td
                                        class="p-3 text-right font-semibold text-foreground"
                                    >
                                        {{ formatQuantity(item.on_hand) }}
                                        <span
                                            class="text-[10px] text-muted-foreground"
                                            >{{ item.unit_symbol }}</span
                                        >
                                    </td>
                                    <td class="p-3 text-right text-amber-300">
                                        {{ formatQuantity(item.reserved) }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-bold text-emerald-300"
                                    >
                                        {{ formatQuantity(item.available) }}
                                    </td>
                                    <td
                                        class="p-3 text-right text-muted-foreground"
                                    >
                                        {{
                                            formatQuantity(item.min_stock_level)
                                        }}
                                    </td>
                                    <td
                                        class="p-3 text-right font-semibold text-sky-300"
                                    >
                                        {{ formatCurrency(item.stock_value) }}
                                    </td>
                                    <td class="p-3 text-muted-foreground">
                                        {{
                                            item.batches[0]?.expiry_date ||
                                            'Không có lô HSD'
                                        }}<span
                                            v-if="item.batches.length"
                                            class="mt-1 block text-[10px]"
                                            >{{ item.batches.length }} lô đang
                                            theo dõi</span
                                        >
                                    </td>
                                </tr>
                                <tr
                                    v-if="expandedId === item.id"
                                    class="bg-muted/10"
                                >
                                    <td colspan="9" class="p-4">
                                        <div
                                            class="grid gap-4 lg:grid-cols-[0.8fr_1.2fr]"
                                        >
                                            <div
                                                class="rounded-xl border border-border bg-background/40 p-4"
                                            >
                                                <div
                                                    class="mb-3 flex items-center gap-2 text-xs font-bold text-foreground"
                                                >
                                                    <Boxes
                                                        class="h-4 w-4 text-indigo-300"
                                                    />
                                                    Đối soát nhanh
                                                </div>
                                                <div
                                                    class="grid grid-cols-2 gap-3 text-xs"
                                                >
                                                    <div>
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            Tồn lý thuyết
                                                        </p>
                                                        <p
                                                            class="mt-1 font-bold text-foreground"
                                                        >
                                                            {{
                                                                formatQuantity(
                                                                    item.theoretical,
                                                                )
                                                            }}
                                                            {{
                                                                item.unit_symbol
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            Chênh lệch
                                                        </p>
                                                        <p
                                                            class="mt-1 font-bold"
                                                            :class="
                                                                item.variance ===
                                                                0
                                                                    ? 'text-emerald-300'
                                                                    : 'text-amber-300'
                                                            "
                                                        >
                                                            {{
                                                                formatQuantity(
                                                                    item.variance,
                                                                )
                                                            }}
                                                            {{
                                                                item.unit_symbol
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            Mức đặt lại
                                                        </p>
                                                        <p
                                                            class="mt-1 font-bold text-foreground"
                                                        >
                                                            {{
                                                                formatQuantity(
                                                                    item.reorder_level,
                                                                )
                                                            }}
                                                            {{
                                                                item.unit_symbol
                                                            }}
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <p
                                                            class="text-muted-foreground"
                                                        >
                                                            Kiểm kê gần nhất
                                                        </p>
                                                        <p
                                                            class="mt-1 font-bold text-foreground"
                                                        >
                                                            {{
                                                                item.last_counted_at ||
                                                                'Chưa kiểm kê'
                                                            }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div
                                                class="rounded-xl border border-border bg-background/40 p-4"
                                            >
                                                <div
                                                    class="mb-3 flex items-center gap-2 text-xs font-bold text-foreground"
                                                >
                                                    <CalendarClock
                                                        class="h-4 w-4 text-orange-300"
                                                    />
                                                    Chi tiết lô hàng
                                                </div>
                                                <div
                                                    v-if="!item.batches.length"
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Chưa có lô hàng khả dụng cho
                                                    mặt hàng này.
                                                </div>
                                                <div
                                                    v-else
                                                    class="grid gap-2 sm:grid-cols-2"
                                                >
                                                    <div
                                                        v-for="batch in item.batches"
                                                        :key="batch.id"
                                                        class="rounded-lg border border-border bg-muted/20 p-3 text-xs"
                                                    >
                                                        <div
                                                            class="flex items-start justify-between gap-2"
                                                        >
                                                            <span
                                                                class="font-mono font-bold text-foreground"
                                                                >{{
                                                                    batch.batch_number
                                                                }}</span
                                                            ><span
                                                                class="rounded-full px-2 py-0.5 text-[10px]"
                                                                :class="
                                                                    batch.is_expired
                                                                        ? 'bg-rose-500/10 text-rose-300'
                                                                        : batch.is_expiring_soon
                                                                          ? 'bg-orange-500/10 text-orange-300'
                                                                          : 'bg-emerald-500/10 text-emerald-300'
                                                                "
                                                                >{{
                                                                    batch.is_expired
                                                                        ? 'Đã quá hạn'
                                                                        : batch.expiry_date
                                                                          ? `Còn ${batch.days_remaining} ngày`
                                                                          : 'Không HSD'
                                                                }}</span
                                                            >
                                                        </div>
                                                        <p
                                                            class="mt-2 text-muted-foreground"
                                                        >
                                                            Còn
                                                            <strong
                                                                class="text-foreground"
                                                                >{{
                                                                    formatQuantity(
                                                                        batch.quantity_remaining,
                                                                    )
                                                                }}
                                                                {{
                                                                    item.unit_symbol
                                                                }}</strong
                                                            >
                                                            ·
                                                            {{
                                                                formatCurrency(
                                                                    batch.unit_cost,
                                                                )
                                                            }}/{{
                                                                item.unit_symbol
                                                            }}
                                                        </p>
                                                        <p
                                                            class="mt-1 text-[10px] text-muted-foreground"
                                                        >
                                                            Nhập
                                                            {{
                                                                batch.purchased_at ||
                                                                '-'
                                                            }}
                                                            · HSD
                                                            {{
                                                                batch.expiry_date ||
                                                                '-'
                                                            }}
                                                            · {{ batch.status }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-wrap items-center gap-4 border-t border-border pt-3 text-[11px] text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1.5"
                        ><ShieldAlert class="h-3.5 w-3.5 text-amber-300" /> Các
                        cảnh báo cần xử lý được xếp lên đầu.</span
                    ><span class="inline-flex items-center gap-1.5"
                        ><ClipboardList class="h-3.5 w-3.5 text-indigo-300" />
                        Cấp phát làm giảm tồn khả dụng qua cơ chế giữ chỗ.</span
                    ><span class="inline-flex items-center gap-1.5"
                        ><AlertTriangle class="h-3.5 w-3.5 text-orange-300" />
                        Hạn dùng được tính theo ngày hiện tại.</span
                    >
                </div>
            </CardContent>
        </Card>
    </div>
</template>
