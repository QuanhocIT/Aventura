<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Download, TrendingDown, TrendingUp } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface LedgerEntry {
    date_label: string;
    type: string;
    icon: string;
    description: string;
    detail: string;
    restaurant: string;
    amount: number;
    amount_fmt: string;
    direction: 'credit' | 'debit';
    status: string;
    ref_id: number;
}
interface PaginatorLink { url: string | null; label: string; active: boolean }
interface Paginator<T> {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    entries: Paginator<LedgerEntry>;
    filters: { date_from?: string; date_to?: string; restaurant_id?: string; entry_type?: string };
    restaurants: Array<{ id: number; name: string; code: string }>;
    totals: { credit: string; debit: string; net: string; credit_raw: number; debit_raw: number };
}>();

const dateFrom     = ref(props.filters.date_from ?? '');
const dateTo       = ref(props.filters.date_to ?? '');
const restaurantId = ref(props.filters.restaurant_id ?? 'all');
const entryType    = ref(props.filters.entry_type ?? 'all');

function applyFilters() {
    router.get('/super-admin/billing/ledger', {
        date_from:     dateFrom.value || undefined,
        date_to:       dateTo.value || undefined,
        restaurant_id: restaurantId.value !== 'all' ? restaurantId.value : undefined,
        entry_type:    entryType.value !== 'all' ? entryType.value : undefined,
    }, { preserveState: true, replace: true });
}

watch([restaurantId, entryType], applyFilters);

function goToPage(url: string | null) {
    if (!url) return;
    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

function exportLedger() {
    const params = new URLSearchParams();
    if (dateFrom.value)  params.set('date_from', dateFrom.value);
    if (dateTo.value)    params.set('date_to', dateTo.value);
    if (restaurantId.value !== 'all') params.set('restaurant_id', restaurantId.value);
    window.open(`/super-admin/billing/ledger/export?${params.toString()}`, '_blank');
}

const typeLabels: Record<string, string> = {
    payment:    'Thanh toán',
    adjustment: 'Điều chỉnh',
    commission: 'Hoa hồng',
};

const typeBadgeClass: Record<string, string> = {
    payment:    'bg-emerald-100 text-emerald-800',
    adjustment: 'bg-amber-100 text-amber-800',
    commission: 'bg-sky-100 text-sky-800',
};

const netPositive = props.totals.credit_raw >= props.totals.debit_raw;
</script>

<template>
    <Head title="Sổ Cái Tài Chính" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <Link href="/super-admin/billing">
                    <Button variant="outline" size="sm">
                        <ArrowLeft class="mr-1.5 size-4" /> Billing Center
                    </Button>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight flex items-center gap-2">
                        <BookOpen class="size-6 text-sky-600" />
                        Sổ Cái Tài Chính
                    </h1>
                    <p class="text-sm text-muted-foreground">Tổng hợp chronological: Hóa đơn + Điều chỉnh + Hoa hồng.</p>
                </div>
            </div>
            <Button variant="outline" size="sm" @click="exportLedger">
                <Download class="mr-1.5 size-4" /> Xuất CSV
            </Button>
        </div>

        <!-- Totals Summary -->
        <div class="grid gap-4 md:grid-cols-3">
            <Card class="border-emerald-200 dark:border-emerald-900/40">
                <CardContent class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-xs text-muted-foreground">Tổng Thu</p>
                        <p class="text-2xl font-bold text-emerald-600">{{ totals.credit }}₫</p>
                    </div>
                    <div class="rounded-xl bg-emerald-100 dark:bg-emerald-900/30 p-2.5">
                        <TrendingUp class="size-5 text-emerald-600" />
                    </div>
                </CardContent>
            </Card>
            <Card class="border-amber-200 dark:border-amber-900/40">
                <CardContent class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-xs text-muted-foreground">Tổng Chi (điều chỉnh + hoa hồng)</p>
                        <p class="text-2xl font-bold text-amber-600">{{ totals.debit }}₫</p>
                    </div>
                    <div class="rounded-xl bg-amber-100 dark:bg-amber-900/30 p-2.5">
                        <TrendingDown class="size-5 text-amber-600" />
                    </div>
                </CardContent>
            </Card>
            <Card :class="netPositive ? 'border-sky-200 dark:border-sky-900/40' : 'border-rose-200 dark:border-rose-900/40'">
                <CardContent class="flex items-center justify-between p-5">
                    <div>
                        <p class="text-xs text-muted-foreground">Thực Nhận (Net)</p>
                        <p class="text-2xl font-bold" :class="netPositive ? 'text-sky-600' : 'text-rose-600'">{{ totals.net }}₫</p>
                    </div>
                    <div :class="['rounded-xl p-2.5', netPositive ? 'bg-sky-100 dark:bg-sky-900/30' : 'bg-rose-100 dark:bg-rose-900/30']">
                        <BookOpen :class="['size-5', netPositive ? 'text-sky-600' : 'text-rose-600']" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base">Bộ Lọc</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-4">
                <div class="grid gap-1.5">
                    <Label>Từ ngày</Label>
                    <Input v-model="dateFrom" type="date" @change="applyFilters" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Đến ngày</Label>
                    <Input v-model="dateTo" type="date" @change="applyFilters" />
                </div>
                <div class="grid gap-1.5">
                    <Label>Nhà hàng</Label>
                    <Select v-model="restaurantId">
                        <SelectTrigger><SelectValue placeholder="Tất cả" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem v-for="r in restaurants" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Loại giao dịch</Label>
                    <Select v-model="entryType">
                        <SelectTrigger><SelectValue placeholder="Tất cả" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem value="payment">Thanh toán</SelectItem>
                            <SelectItem value="adjustment">Điều chỉnh</SelectItem>
                            <SelectItem value="commission">Hoa hồng</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </CardContent>
        </Card>

        <!-- Ledger Table -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center justify-between text-base">
                    <span>Sổ Cái <span class="text-sm font-normal text-muted-foreground">({{ entries.total }} giao dịch)</span></span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="entries.data.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                    Không có giao dịch nào trong khoảng thời gian đã chọn.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="pb-3 text-left font-medium">Ngày</th>
                                <th class="pb-3 text-left font-medium">Loại</th>
                                <th class="pb-3 text-left font-medium">Mô tả</th>
                                <th class="pb-3 text-left font-medium">Nhà hàng</th>
                                <th class="pb-3 text-right font-medium">Số tiền</th>
                                <th class="pb-3 text-left font-medium">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(entry, idx) in entries.data"
                                :key="idx"
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td class="py-3 pr-4 text-xs text-muted-foreground whitespace-nowrap">{{ entry.date_label }}</td>
                                <td class="py-3 pr-4">
                                    <Badge :class="typeBadgeClass[entry.type] || 'bg-slate-100 text-slate-800'">
                                        {{ entry.icon }} {{ typeLabels[entry.type] || entry.type }}
                                    </Badge>
                                </td>
                                <td class="py-3 pr-4">
                                    <p class="font-medium">{{ entry.description }}</p>
                                    <p class="text-xs text-muted-foreground">{{ entry.detail }}</p>
                                </td>
                                <td class="py-3 pr-4 text-muted-foreground">{{ entry.restaurant }}</td>
                                <td class="py-3 pr-4 text-right font-semibold whitespace-nowrap" :class="entry.direction === 'credit' ? 'text-emerald-600' : 'text-rose-600'">
                                    {{ entry.direction === 'credit' ? '+' : '−' }}{{ entry.amount_fmt }}₫
                                </td>
                                <td class="py-3">
                                    <Badge class="bg-slate-100 text-slate-700">{{ entry.status }}</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="entries.last_page > 1" class="flex flex-wrap justify-center gap-1 pt-4">
                    <button
                        v-for="link in entries.links"
                        :key="link.label"
                        :disabled="!link.url"
                        :class="[
                            'rounded px-3 py-1 text-xs transition',
                            link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted',
                            !link.url ? 'cursor-not-allowed opacity-40' : '',
                        ]"
                        @click="goToPage(link.url)"
                        v-html="link.label"
                    />
                </div>
            </CardContent>
        </Card>
    </div>
</template>
