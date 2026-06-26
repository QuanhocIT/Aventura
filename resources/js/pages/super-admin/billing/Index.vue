<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BarChart2, BookOpen, Download, Filter, LayoutGrid, ReceiptText, RefreshCcw, Search, WalletCards, Wallet, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface PaginatorLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator<T> {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    filters: { restaurant_id?: string; status?: string; type?: string; search?: string };
    restaurants: Array<{ id: number; name: string; code: string }>;
    invoices: Paginator<{ id: number; invoice_number: string; restaurant: string; status: string; type: string; total: string; currency: string; due_on: string; sent_at: string }>;
    webhooks: Paginator<{ id: number; provider: string; status: string; transaction_code: string; event_type: string; processed_at: string }>;
    adjustments: Paginator<{ id: number; restaurant: string; type: string; days: number; discount_amount: string; reason: string; creator: string; created_at: string }>;
}>();

const restaurantId = ref(props.filters.restaurant_id ?? 'all');
const status = ref(props.filters.status ?? 'all');
const type = ref(props.filters.type ?? 'all');
const search = ref(props.filters.search ?? '');

let timer: ReturnType<typeof setTimeout> | undefined;

function applyFilters() {
    router.get('/super-admin/billing', {
        restaurant_id: restaurantId.value === 'all' ? undefined : restaurantId.value,
        status: status.value === 'all' ? undefined : status.value,
        type: type.value === 'all' ? undefined : type.value,
        search: search.value || undefined,
    }, { preserveState: true, replace: true });
}

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 350);
});

function goToPage(url: string | null) {
    if (!url) {
return;
}

    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

const stateColor: Record<string, string> = {
    active: 'bg-emerald-100 text-emerald-800',
    expired: 'bg-rose-100 text-rose-800',
    suspended: 'bg-amber-100 text-amber-800',
    pending: 'bg-slate-100 text-slate-800',
    generated: 'bg-sky-100 text-sky-800',
    sent: 'bg-indigo-100 text-indigo-800',
    processed: 'bg-emerald-100 text-emerald-800',
    orphaned: 'bg-rose-100 text-rose-800',
};

function resendInvoice(id: number) {
    router.post(`/super-admin/billing/invoices/${id}/resend`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã gửi lại email!'),
        onError: () => toast.error('Gửi lại email thất bại!'),
    });
}

function regenerateInvoice(id: number) {
    router.post(`/super-admin/billing/invoices/${id}/regenerate`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã tạo lại hóa đơn!'),
        onError: () => toast.error('Tạo lại hóa đơn thất bại!'),
    });
}

function retryWebhook(id: number) {
    router.post(`/super-admin/billing/webhooks/${id}/retry`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã retry webhook!'),
        onError: () => toast.error('Retry webhook thất bại!'),
    });
}

function exportCsv() {
    window.open('/super-admin/billing/export', '_blank');
}

// Write-off
const writeOffInvoiceId = ref<number | null>(null);
const writeOffInvoiceNum = ref('');
const writeOffReason = ref('');
const writeOffLoading = ref(false);

function openWriteOff(id: number, num: string) {
    writeOffInvoiceId.value = id;
    writeOffInvoiceNum.value = num;
    writeOffReason.value = '';
}

function closeWriteOff() {
    writeOffInvoiceId.value = null;
    writeOffInvoiceNum.value = '';
    writeOffReason.value = '';
}

function submitWriteOff() {
    if (!writeOffInvoiceId.value || !writeOffReason.value.trim()) return;
    writeOffLoading.value = true;
    router.patch(`/super-admin/billing/invoices/${writeOffInvoiceId.value}/write-off`, {
        reason: writeOffReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Đã đánh dấu nợ xấu thành công!');
            closeWriteOff();
        },
        onError: (e: Record<string, string>) => toast.error(Object.values(e).join(' ')),
        onFinish: () => { writeOffLoading.value = false; },
    });
}
</script>

<template>
    <Head title="Billing Center" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Billing Center</h1>
                <p class="text-sm text-muted-foreground">Theo dõi hóa đơn, webhook và điều chỉnh billing toàn hệ thống.</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <Link href="/super-admin/billing/analytics">
                    <Button variant="outline" size="sm">
                        <BarChart2 class="mr-1.5 size-4" /> Analytics
                    </Button>
                </Link>
                <Link href="/super-admin/billing/ledger">
                    <Button variant="outline" size="sm">
                        <BookOpen class="mr-1.5 size-4" /> Sổ Cái
                    </Button>
                </Link>
                <Link href="/super-admin/billing/revenue-recognition">
                    <Button variant="outline" size="sm">
                        <Wallet class="mr-1.5 size-4" /> Doanh Thu
                    </Button>
                </Link>
                <Link href="/super-admin/billing/dunning">
                    <Button variant="outline" size="sm">
                        <RefreshCcw class="mr-1.5 size-4" /> Dunning
                    </Button>
                </Link>
                <Link href="/super-admin/billing/lifecycle">
                    <Button variant="outline" size="sm">
                        <LayoutGrid class="mr-1.5 size-4" /> Lifecycle
                    </Button>
                </Link>
                <Button variant="outline" size="sm" @click="applyFilters">
                    <RefreshCcw class="mr-1.5 size-4" /> Làm mới
                </Button>
                <Button variant="outline" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 size-4" /> Export CSV
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm text-muted-foreground">Hóa đơn</p>
                        <p class="text-2xl font-bold">{{ invoices.total }}</p>
                    </div>
                    <ReceiptText class="size-8 text-sky-600" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm text-muted-foreground">Webhook</p>
                        <p class="text-2xl font-bold">{{ webhooks.total }}</p>
                    </div>
                    <RefreshCcw class="size-8 text-violet-600" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm text-muted-foreground">Điều chỉnh</p>
                        <p class="text-2xl font-bold">{{ adjustments.total }}</p>
                    </div>
                    <WalletCards class="size-8 text-emerald-600" />
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base"><Filter class="size-4" /> Bộ lọc</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-4">
                <div class="grid gap-1.5">
                    <Label>Nhà hàng</Label>
                    <Select v-model="restaurantId" @update:modelValue="applyFilters">
                        <SelectTrigger><SelectValue placeholder="Tất cả" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem v-for="restaurant in restaurants" :key="restaurant.id" :value="String(restaurant.id)">
                                {{ restaurant.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Trạng thái</Label>
                    <Select v-model="status" @update:modelValue="applyFilters">
                        <SelectTrigger><SelectValue placeholder="Tất cả" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem value="pending">Pending</SelectItem>
                            <SelectItem value="generated">Generated</SelectItem>
                            <SelectItem value="sent">Sent</SelectItem>
                            <SelectItem value="processed">Processed</SelectItem>
                            <SelectItem value="expired">Expired</SelectItem>
                            <SelectItem value="suspended">Suspended</SelectItem>
                            <SelectItem value="orphaned">Orphaned</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Loại</Label>
                    <Select v-model="type" @update:modelValue="applyFilters">
                        <SelectTrigger><SelectValue placeholder="Tất cả" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem value="payment_success">Payment success</SelectItem>
                            <SelectItem value="upcoming_renewal">Upcoming renewal</SelectItem>
                            <SelectItem value="extend">Extend</SelectItem>
                            <SelectItem value="discount">Discount</SelectItem>
                            <SelectItem value="trial">Trial</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Tìm kiếm</Label>
                    <div class="relative">
                        <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                        <Input v-model="search" placeholder="Mã hóa đơn, transaction, lý do..." class="pl-9" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-3">
            <Card class="xl:col-span-2">
                <CardHeader class="pb-3">
                    <CardTitle>Hóa đơn <span class="text-sm font-normal text-muted-foreground">(trang {{ invoices.current_page }}/{{ invoices.last_page }})</span></CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="invoice in invoices.data" :key="invoice.id" class="rounded-xl border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ invoice.invoice_number }}</p>
                                <p class="text-xs text-muted-foreground">{{ invoice.restaurant }}</p>
                            </div>
                            <Badge :class="stateColor[invoice.status] || 'bg-slate-100 text-slate-800'">
                                {{ invoice.status }}
                            </Badge>
                        </div>
                        <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm text-muted-foreground">
                            <span>{{ invoice.type }}</span>
                            <span>{{ invoice.total }} {{ invoice.currency }}</span>
                            <span>Hạn {{ invoice.due_on || 'Chưa xác định' }}</span>
                            <span>Gửi {{ invoice.sent_at || 'Chưa gửi' }}</span>
                        </div>
                        <div class="mt-2 flex gap-2 flex-wrap">
                            <Button size="sm" variant="outline" @click="resendInvoice(invoice.id)">Gửi lại email</Button>
                            <Button size="sm" variant="outline" @click="regenerateInvoice(invoice.id)">Tạo lại hóa đơn</Button>
                            <a :href="`/super-admin/billing/invoices/${invoice.id}/download`" target="_blank">
                                <Button size="sm" variant="outline">
                                    <Download class="mr-1.5 size-3.5" /> Tải PDF
                                </Button>
                            </a>
                            <!-- Write-off button: only for pending invoices -->
                            <Button
                                v-if="invoice.status === 'pending'"
                                size="sm"
                                variant="outline"
                                class="border-rose-300 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20"
                                @click="openWriteOff(invoice.id, invoice.invoice_number)"
                            >
                                <XCircle class="mr-1.5 size-3.5" /> Nợ xấu
                            </Button>
                        </div>
                    </div>
                    <p v-if="!invoices.data.length" class="py-10 text-center text-sm text-muted-foreground">Không có hóa đơn phù hợp.</p>

                    <!-- Invoices pagination -->
                    <div v-if="invoices.last_page > 1" class="flex flex-wrap justify-center gap-1 pt-2">
                        <button
                            v-for="link in invoices.links"
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

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle>Webhook <span class="text-sm font-normal text-muted-foreground">(trang {{ webhooks.current_page }}/{{ webhooks.last_page }})</span></CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div v-for="webhook in webhooks.data" :key="webhook.id" class="rounded-xl border p-4 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ webhook.provider }}</span>
                            <Badge :class="stateColor[webhook.status] || 'bg-slate-100 text-slate-800'">{{ webhook.status }}</Badge>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">{{ webhook.transaction_code || 'Không có mã giao dịch' }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ webhook.event_type || 'Không rõ loại sự kiện' }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ webhook.processed_at || 'Chưa xử lý' }}</p>
                        <div class="mt-2">
                            <Button size="sm" variant="outline" @click="retryWebhook(webhook.id)">Retry webhook</Button>
                        </div>
                    </div>

                    <p v-if="!webhooks.data.length" class="py-10 text-center text-sm text-muted-foreground">Không có webhook phù hợp.</p>

                    <!-- Webhooks pagination -->
                    <div v-if="webhooks.last_page > 1" class="flex flex-wrap justify-center gap-1 pt-2">
                        <button
                            v-for="link in webhooks.links"
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

        <Card>
            <CardHeader class="pb-3">
                <CardTitle>Điều chỉnh billing <span class="text-sm font-normal text-muted-foreground">(trang {{ adjustments.current_page }}/{{ adjustments.last_page }})</span></CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-for="adjustment in adjustments.data" :key="adjustment.id" class="rounded-xl border p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ adjustment.restaurant }}</p>
                            <p class="text-xs text-muted-foreground">{{ adjustment.reason || 'Không có ghi chú' }}</p>
                        </div>
                        <Badge class="bg-emerald-100 text-emerald-800">{{ adjustment.type }}</Badge>
                    </div>
                    <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm text-muted-foreground">
                        <span>+{{ adjustment.days }} ngày</span>
                        <span>Giảm {{ adjustment.discount_amount }} VND</span>
                        <span>{{ adjustment.creator }}</span>
                        <span>{{ adjustment.created_at }}</span>
                    </div>
                </div>
                <p v-if="!adjustments.data.length" class="py-10 text-center text-sm text-muted-foreground">Không có điều chỉnh nào.</p>

                <!-- Adjustments pagination -->
                <div v-if="adjustments.last_page > 1" class="flex flex-wrap justify-center gap-1 pt-2">
                    <button
                        v-for="link in adjustments.links"
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

        <!-- Write-off Dialog -->
        <div
            v-if="writeOffInvoiceId !== null"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
            @click.self="closeWriteOff"
        >
            <div class="bg-background rounded-2xl shadow-2xl w-full max-w-md p-6">
                <h2 class="text-lg font-bold text-rose-600 mb-1">⚠️ Đánh dấu Nợ Xấu</h2>
                <p class="text-sm text-muted-foreground mb-4">
                    Hóa đơn <strong>{{ writeOffInvoiceNum }}</strong> sẽ bị đánh dấu là không thể thu hồi.
                </p>
                <div class="space-y-2">
                    <Label>Lý do write-off <span class="text-rose-500">*</span></Label>
                    <textarea
                        v-model="writeOffReason"
                        rows="3"
                        class="w-full rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400 bg-background"
                        placeholder="Nhập lý do (bắt buộc)..."
                    />
                </div>
                <div class="flex gap-3 mt-4 justify-end">
                    <Button variant="outline" @click="closeWriteOff">Hủy</Button>
                    <Button
                        class="bg-rose-600 text-white hover:bg-rose-700"
                        :disabled="!writeOffReason.trim() || writeOffLoading"
                        @click="submitWriteOff"
                    >
                        {{ writeOffLoading ? 'Đang xử lý...' : 'Xác nhận Nợ Xấu' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
