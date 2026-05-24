<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ReceiptText,
    RefreshCcw,
    WalletCards,
    Search,
    Filter,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    filters: {
        restaurant_id?: string;
        status?: string;
        type?: string;
        search?: string;
    };
    restaurants: Array<{ id: number; name: string; code: string }>;
    invoices: Array<{
        id: number;
        invoice_number: string;
        restaurant: string;
        status: string;
        type: string;
        total: string;
        currency: string;
        due_on: string;
        sent_at: string;
    }>;
    webhooks: Array<{
        id: number;
        provider: string;
        status: string;
        transaction_code: string;
        event_type: string;
        processed_at: string;
    }>;
    adjustments: Array<{
        id: number;
        restaurant: string;
        type: string;
        days: number;
        discount_amount: string;
        reason: string;
        creator: string;
        created_at: string;
    }>;
}>();

const restaurantId = ref(props.filters.restaurant_id ?? 'all');
const status = ref(props.filters.status ?? 'all');
const type = ref(props.filters.type ?? 'all');
const search = ref(props.filters.search ?? '');

let timer: ReturnType<typeof setTimeout> | undefined;
function applyFilters() {
    router.get(
        '/super-admin/billing',
        {
            restaurant_id:
                restaurantId.value === 'all' ? undefined : restaurantId.value,
            status: status.value === 'all' ? undefined : status.value,
            type: type.value === 'all' ? undefined : type.value,
            search: search.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 350);
});

const invoiceCount = computed(() => props.invoices.length);
const webhookCount = computed(() => props.webhooks.length);
const adjustmentCount = computed(() => props.adjustments.length);

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
    router.post(
        `/super-admin/billing/invoices/${id}/resend`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => alert('Đã gửi lại email!'),
            onError: () => alert('Gửi lại email thất bại!'),
        },
    );
}

function regenerateInvoice(id: number) {
    router.post(
        `/super-admin/billing/invoices/${id}/regenerate`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => alert('Đã tạo lại hóa đơn!'),
            onError: () => alert('Tạo lại hóa đơn thất bại!'),
        },
    );
}

function retryWebhook(id: number) {
    router.post(
        `/super-admin/billing/webhooks/${id}/retry`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => alert('Đã retry webhook!'),
            onError: () => alert('Retry webhook thất bại!'),
        },
    );
}

function exportCsv() {
    window.open('/super-admin/billing/export', '_blank');
}
</script>

<template>
    <Head title="Billing Center" />

    <div class="flex flex-col gap-6 p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">
                    Billing Center
                </h1>
                <p class="text-sm text-muted-foreground">
                    Theo dõi hóa đơn, webhook và điều chỉnh billing toàn hệ
                    thống.
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" @click="applyFilters">
                    <RefreshCcw class="mr-2 size-4" /> Làm mới
                </Button>
                <Button variant="outline" @click="exportCsv">
                    Export CSV
                </Button>
                <Button variant="outline" disabled> Export PDF </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Hoa don hien thi
                        </p>
                        <p class="text-2xl font-bold">{{ invoiceCount }}</p>
                    </div>
                    <ReceiptText class="size-8 text-sky-600" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Webhook hien thi
                        </p>
                        <p class="text-2xl font-bold">{{ webhookCount }}</p>
                    </div>
                    <RefreshCcw class="size-8 text-violet-600" />
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center justify-between p-4">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Dieu chinh hien thi
                        </p>
                        <p class="text-2xl font-bold">{{ adjustmentCount }}</p>
                    </div>
                    <WalletCards class="size-8 text-emerald-600" />
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="flex items-center gap-2 text-base"
                    ><Filter class="size-4" /> Bo loc</CardTitle
                >
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-4">
                <div class="grid gap-1.5">
                    <Label>Nha hang</Label>
                    <Select
                        v-model="restaurantId"
                        @update:modelValue="applyFilters"
                    >
                        <SelectTrigger
                            ><SelectValue placeholder="Tat ca"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tat ca</SelectItem>
                            <SelectItem
                                v-for="restaurant in restaurants"
                                :key="restaurant.id"
                                :value="String(restaurant.id)"
                            >
                                {{ restaurant.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Trang thai</Label>
                    <Select v-model="status" @update:modelValue="applyFilters">
                        <SelectTrigger
                            ><SelectValue placeholder="Tat ca"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tat ca</SelectItem>
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
                    <Label>Loai</Label>
                    <Select v-model="type" @update:modelValue="applyFilters">
                        <SelectTrigger
                            ><SelectValue placeholder="Tat ca"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tat ca</SelectItem>
                            <SelectItem value="payment_success"
                                >Payment success</SelectItem
                            >
                            <SelectItem value="upcoming_renewal"
                                >Upcoming renewal</SelectItem
                            >
                            <SelectItem value="extend">Extend</SelectItem>
                            <SelectItem value="discount">Discount</SelectItem>
                            <SelectItem value="trial">Trial</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label>Tim kiem</Label>
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-3 size-4 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            placeholder="Ma hoa don, transaction, ly do..."
                            class="pl-9"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-3">
            <Card class="xl:col-span-2">
                <CardHeader class="pb-3">
                    <CardTitle>Hoa don gan day</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="rounded-xl border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ invoice.invoice_number }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ invoice.restaurant }}
                                </p>
                            </div>
                            <Badge
                                :class="
                                    stateColor[invoice.status] ||
                                    'bg-slate-100 text-slate-800'
                                "
                            >
                                {{ invoice.status }}
                            </Badge>
                        </div>
                        <div
                            class="mt-3 flex flex-wrap justify-between gap-2 text-sm text-muted-foreground"
                        >
                            <span>{{ invoice.type }}</span>
                            <span
                                >{{ invoice.total }}
                                {{ invoice.currency }}</span
                            >
                            <span>Hạn {{ invoice.due_on || '�' }}</span>
                            <span>Gửi {{ invoice.sent_at || 'Chưa gửi' }}</span>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="resendInvoice(invoice.id)"
                                >Gửi lại email</Button
                            >
                            <Button
                                size="sm"
                                variant="outline"
                                @click="regenerateInvoice(invoice.id)"
                                >Tạo lại hóa đơn</Button
                            >
                        </div>
                    </div>
                    <p
                        v-if="!invoices.length"
                        class="py-10 text-center text-sm text-muted-foreground"
                    >
                        Khong co hoa don phu hop.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <CardTitle>Webhook</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="webhook in webhooks"
                        :key="webhook.id"
                        class="rounded-xl border p-4 text-sm"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">{{
                                webhook.provider
                            }}</span>
                            <Badge
                                :class="
                                    stateColor[webhook.status] ||
                                    'bg-slate-100 text-slate-800'
                                "
                                >{{ webhook.status }}</Badge
                            >
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ webhook.transaction_code || '�' }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ webhook.event_type || '�' }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ webhook.processed_at || 'Chưa xử lý' }}
                        </p>
                        <div class="mt-2">
                            <Button
                                size="sm"
                                variant="outline"
                                @click="retryWebhook(webhook.id)"
                                >Retry webhook</Button
                            >
                        </div>
                    </div>

                    <p
                        v-if="!webhooks.length"
                        class="py-10 text-center text-sm text-muted-foreground"
                    >
                        Khong co webhook phu hop.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader class="pb-3">
                <CardTitle>Dieu chinh billing</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    v-for="adjustment in adjustments"
                    :key="adjustment.id"
                    class="rounded-xl border p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-medium">
                                {{ adjustment.restaurant }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ adjustment.reason || 'Khong co ghi chu' }}
                            </p>
                        </div>
                        <Badge class="bg-emerald-100 text-emerald-800">{{
                            adjustment.type
                        }}</Badge>
                    </div>
                    <div
                        class="mt-3 flex flex-wrap justify-between gap-2 text-sm text-muted-foreground"
                    >
                        <span>+{{ adjustment.days }} ngay</span>
                        <span>Giam {{ adjustment.discount_amount }} VND</span>
                        <span>{{ adjustment.creator }}</span>
                        <span>{{ adjustment.created_at }}</span>
                    </div>
                </div>
                <p
                    v-if="!adjustments.length"
                    class="py-10 text-center text-sm text-muted-foreground"
                >
                    Khong co dieu chinh nao.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
