<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { BarChart2, BookOpen, Download, LayoutGrid, ReceiptText, RefreshCcw, Search, WalletCards, Wallet, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, StatCard, FilterBar, StatusBadge, Pagination, GradientDivider, EmptyState } from '@/components/super-admin';
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
const showWriteOff = ref(false);
const writeOffInvoiceId = ref<number | null>(null);
const writeOffInvoiceNum = ref('');
const writeOffReason = ref('');
const writeOffLoading = ref(false);

function openWriteOff(id: number, num: string) {
    writeOffInvoiceId.value = id;
    writeOffInvoiceNum.value = num;
    writeOffReason.value = '';
    showWriteOff.value = true;
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
            showWriteOff.value = false;
        },
        onError: (e: Record<string, string>) => toast.error(Object.values(e).join(' ')),
        onFinish: () => { writeOffLoading.value = false; },
    });
}
</script>

<template>
    <Head title="Billing Center" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Billing Center"
            subtitle="Theo dõi hóa đơn, webhook và điều chỉnh billing toàn hệ thống."
            :icon="ReceiptText"
        >
            <template #actions>
                <Link href="/super-admin/billing/analytics">
                    <Button variant="outline" size="sm"><BarChart2 class="mr-1.5 size-4" /> Analytics</Button>
                </Link>
                <Link href="/super-admin/billing/ledger">
                    <Button variant="outline" size="sm"><BookOpen class="mr-1.5 size-4" /> Sổ Cái</Button>
                </Link>
                <Link href="/super-admin/billing/revenue-recognition">
                    <Button variant="outline" size="sm"><Wallet class="mr-1.5 size-4" /> Doanh Thu</Button>
                </Link>
                <Link href="/super-admin/billing/dunning">
                    <Button variant="outline" size="sm"><RefreshCcw class="mr-1.5 size-4" /> Dunning</Button>
                </Link>
                <Link href="/super-admin/billing/lifecycle">
                    <Button variant="outline" size="sm"><LayoutGrid class="mr-1.5 size-4" /> Lifecycle</Button>
                </Link>
                <Button variant="outline" size="sm" @click="applyFilters">
                    <RefreshCcw class="mr-1.5 size-4" /> Làm mới
                </Button>
                <Button variant="outline" size="sm" @click="exportCsv">
                    <Download class="mr-1.5 size-4" /> Export CSV
                </Button>
            </template>
        </PageHeader>

        <!-- Stats -->
        <div class="grid gap-4 md:grid-cols-3">
            <StatCard label="Hóa đơn" :value="invoices.total" :icon="ReceiptText" color="sky" class="" />
            <StatCard label="Webhook" :value="webhooks.total" :icon="RefreshCcw" color="violet" class="" />
            <StatCard label="Điều chỉnh" :value="adjustments.total" :icon="WalletCards" color="emerald" class="" />
        </div>

        <!-- Filters -->
        <FilterBar>
            <div class="grid flex-1 gap-1.5">
                <Label class="text-[10px] uppercase tracking-wider">Nhà hàng</Label>
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
                <Label class="text-[10px] uppercase tracking-wider">Trạng thái</Label>
                <Select v-model="status" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-[150px]"><SelectValue placeholder="Tất cả" /></SelectTrigger>
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
                <Label class="text-[10px] uppercase tracking-wider">Loại</Label>
                <Select v-model="type" @update:modelValue="applyFilters">
                    <SelectTrigger class="w-[170px]"><SelectValue placeholder="Tất cả" /></SelectTrigger>
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
            <div class="grid flex-1 gap-1.5">
                <Label class="text-[10px] uppercase tracking-wider">Tìm kiếm</Label>
                <div class="relative">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" placeholder="Mã hóa đơn, transaction..." class="pl-9" />
                </div>
            </div>
        </FilterBar>

        <!-- Invoices & Webhooks -->
        <div class="grid gap-6 xl:grid-cols-3">
            <!-- Invoices -->
            <Card class="xl:col-span-2 stagger-3">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2">
                        <ReceiptText class="size-4 text-sky-500" />
                        Hóa đơn
                        <span class="text-sm font-normal text-muted-foreground">(trang {{ invoices.current_page }}/{{ invoices.last_page }})</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="(invoice, idx) in invoices.data"
                        :key="invoice.id"
                        class="rounded-2xl border border-border/40 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[var(--shadow-elevated)] hover:border-white/20"
                        :style="{ animationDelay: `${idx * 40}ms` }"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ invoice.invoice_number }}</p>
                                <p class="text-xs text-muted-foreground">{{ invoice.restaurant }}</p>
                            </div>
                            <StatusBadge :status="invoice.status" />
                        </div>
                        <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm text-muted-foreground">
                            <span>{{ invoice.type }}</span>
                            <span class="font-mono font-medium tabular-nums text-foreground">{{ invoice.total }} {{ invoice.currency }}</span>
                            <span>Hạn {{ invoice.due_on || 'Chưa xác định' }}</span>
                            <span>Gửi {{ invoice.sent_at || 'Chưa gửi' }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <Button size="sm" variant="outline" @click="resendInvoice(invoice.id)">Gửi lại email</Button>
                            <Button size="sm" variant="outline" @click="regenerateInvoice(invoice.id)">Tạo lại hóa đơn</Button>
                            <a :href="`/super-admin/billing/invoices/${invoice.id}/download`" target="_blank">
                                <Button size="sm" variant="outline"><Download class="mr-1.5 size-3.5" /> Tải PDF</Button>
                            </a>
                            <Button
                                v-if="invoice.status === 'pending'"
                                size="sm"
                                variant="outline"
                                class="border-rose-500/30 text-rose-600 hover:bg-rose-500/10 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                @click="openWriteOff(invoice.id, invoice.invoice_number)"
                            >
                                <XCircle class="mr-1.5 size-3.5" /> Nợ xấu
                            </Button>
                        </div>
                    </div>

                    <EmptyState
                        v-if="!invoices.data.length"
                        :icon="ReceiptText"
                        title="Không có hóa đơn phù hợp"
                        description="Thử thay đổi bộ lọc để tìm hóa đơn."
                    />

                    <Pagination v-if="invoices.last_page > 1" :links="invoices.links" />
                </CardContent>
            </Card>

            <!-- Webhooks -->
            <Card class="">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2">
                        <RefreshCcw class="size-4 text-violet-500" />
                        Webhook
                        <span class="text-sm font-normal text-muted-foreground">(trang {{ webhooks.current_page }}/{{ webhooks.last_page }})</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="(webhook, idx) in webhooks.data"
                        :key="webhook.id"
                        class="rounded-2xl border border-border/40 p-4 text-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[var(--shadow-elevated)]"
                        :style="{ animationDelay: `${idx * 40}ms` }"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{ webhook.provider }}</span>
                            <StatusBadge :status="webhook.status" />
                        </div>
                        <p class="mt-2 font-mono text-xs text-muted-foreground">{{ webhook.transaction_code || 'Không có mã giao dịch' }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ webhook.event_type || 'Không rõ loại sự kiện' }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ webhook.processed_at || 'Chưa xử lý' }}</p>
                        <div class="mt-2">
                            <Button size="sm" variant="outline" @click="retryWebhook(webhook.id)">Retry webhook</Button>
                        </div>
                    </div>

                    <EmptyState
                        v-if="!webhooks.data.length"
                        :icon="RefreshCcw"
                        title="Không có webhook phù hợp"
                    />

                    <Pagination v-if="webhooks.last_page > 1" :links="webhooks.links" />
                </CardContent>
            </Card>
        </div>

        <GradientDivider />

        <!-- Adjustments -->
        <Card class="">
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2">
                    <WalletCards class="size-4 text-emerald-500" />
                    Điều chỉnh billing
                    <span class="text-sm font-normal text-muted-foreground">(trang {{ adjustments.current_page }}/{{ adjustments.last_page }})</span>
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="(adjustment, idx) in adjustments.data"
                    :key="adjustment.id"
                    class="rounded-2xl border border-border/40 p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[var(--shadow-elevated)]"
                    :style="{ animationDelay: `${idx * 40}ms` }"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ adjustment.restaurant }}</p>
                            <p class="text-xs text-muted-foreground">{{ adjustment.reason || 'Không có ghi chú' }}</p>
                        </div>
                        <StatusBadge :status="adjustment.type">{{ adjustment.type }}</StatusBadge>
                    </div>
                    <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm text-muted-foreground">
                        <span class="font-mono font-medium text-emerald-600 dark:text-emerald-400">+{{ adjustment.days }} ngày</span>
                        <span>Giảm <span class="font-mono font-medium text-foreground">{{ adjustment.discount_amount }}</span> VND</span>
                        <span>{{ adjustment.creator }}</span>
                        <span>{{ adjustment.created_at }}</span>
                    </div>
                </div>

                <EmptyState
                    v-if="!adjustments.data.length"
                    :icon="WalletCards"
                    title="Không có điều chỉnh nào"
                />

                <Pagination v-if="adjustments.last_page > 1" :links="adjustments.links" />
            </CardContent>
        </Card>
    </div>

    <!-- Write-off Dialog -->
    <Dialog v-model:open="showWriteOff">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle class="text-rose-600">⚠️ Đánh dấu Nợ Xấu</DialogTitle>
            </DialogHeader>
            <p class="text-sm text-muted-foreground">
                Hóa đơn <strong>{{ writeOffInvoiceNum }}</strong> sẽ bị đánh dấu là không thể thu hồi.
            </p>
            <div class="space-y-2">
                <Label>Lý do write-off <span class="text-rose-500">*</span></Label>
                <textarea
                    v-model="writeOffReason"
                    rows="3"
                    class="w-full rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-400"
                    placeholder="Nhập lý do (bắt buộc)..."
                />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="showWriteOff = false">Hủy</Button>
                <Button
                    class="bg-rose-600 text-white hover:bg-rose-700"
                    :disabled="!writeOffReason.trim() || writeOffLoading"
                    @click="submitWriteOff"
                >
                    {{ writeOffLoading ? 'Đang xử lý...' : 'Xác nhận Nợ Xấu' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
