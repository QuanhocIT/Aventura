<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Coins,
    Hash,
    Layers,
    Plus,
    QrCode,
    Tag,
    Users,
    X,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import {
    EmptyState,
    PageHeader,
    Pagination,
    ProgressBar,
    StatusBadge,
} from '@/components/super-admin';
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

interface Batch {
    id: number;
    name: string;
    template_name: string | null;
    template_season: string | null;
    code_prefix: string;
    code_count: number;
    generated_count: number;
    discount_type: 'percent' | 'fixed';
    discount_value: number;
    status: 'pending' | 'generating' | 'completed' | 'failed';
    progress: number;
    has_qr_sheet: boolean;
    starts_at: string | null;
    expires_at: string | null;
    created_at: string;
}

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    batches: Paginator<Batch>;
}>();

// Map trạng thái batch sang bảng màu sẵn có của StatusBadge (không có key 'completed'/'generating' riêng).
const statusColorKey: Record<Batch['status'], string> = {
    pending: 'pending',
    generating: 'in_progress',
    completed: 'processed',
    failed: 'failed',
};

const statusLabel: Record<Batch['status'], string> = {
    pending: 'Chờ xử lý',
    generating: 'Đang tạo mã',
    completed: 'Hoàn tất',
    failed: 'Thất bại',
};

function goToPage(url: string | null) {
    if (!url) {
        return;
    }

    router.get(url, {}, { preserveState: true, preserveScroll: true });
}

const showForm = ref(false);

const formData = ref({
    name: '',
    code_prefix: '',
    code_count: 100,
    discount_type: 'percent' as 'percent' | 'fixed',
    discount_value: 10,
    max_uses_per_code: 1,
    starts_at: '',
    expires_at: '',
});

function openCreateForm() {
    formData.value = {
        name: '',
        code_prefix: '',
        code_count: 100,
        discount_type: 'percent',
        discount_value: 10,
        max_uses_per_code: 1,
        starts_at: '',
        expires_at: '',
    };
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
}

function submitForm() {
    const data = {
        ...formData.value,
        starts_at: formData.value.starts_at || null,
        expires_at: formData.value.expires_at || null,
    };

    router.post('/super-admin/coupons/batches', data, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                'Đã bắt đầu tạo lô coupon, hệ thống sẽ xử lý ngầm...',
            );
            closeForm();
        },
        onError: (e: Record<string, string>) =>
            toast.error(Object.values(e)[0] as string),
    });
}
</script>

<template>
    <Head title="Coupon hàng loạt" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Coupon hàng loạt"
            subtitle="Tạo và theo dõi các lô mã giảm giá được sinh tự động hàng loạt."
            :icon="Layers"
        >
            <template #actions>
                <Button
                    variant="outline"
                    class="cursor-pointer rounded-xl text-xs font-bold"
                    @click="router.get('/super-admin/coupons')"
                >
                    <ArrowLeft class="mr-2 size-4" /> Về danh sách coupon
                </Button>
                <Button
                    class="cursor-pointer rounded-xl bg-primary text-xs font-bold text-primary-foreground shadow-sm"
                    @click="openCreateForm"
                >
                    <Plus class="mr-2 size-4" /> Tạo lô mới
                </Button>
            </template>
        </PageHeader>

        <Card
            class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
        >
            <CardHeader class="border-b border-border/40 bg-muted/10 pb-3">
                <CardTitle class="text-sm font-bold">
                    Danh sách lô coupon
                    <span class="ml-1 text-xs font-bold text-muted-foreground"
                        >({{ batches.total }} lô)</span
                    >
                </CardTitle>
            </CardHeader>
            <CardContent class="pt-4">
                <EmptyState
                    v-if="!batches.data.length"
                    :icon="Layers"
                    title="Chưa có lô coupon nào"
                    description="Tạo lô mới để sinh hàng loạt mã giảm giá dùng cho chiến dịch marketing hoặc đối tác."
                />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs font-semibold">
                        <thead>
                            <tr
                                class="border-b border-border/60 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >
                                <th class="pb-3 text-left font-black">
                                    Tên lô
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Tiền tố mã
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Giá trị
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Tiến độ
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Hiệu lực
                                </th>
                                <th class="pb-3 text-left font-black">
                                    Trạng thái
                                </th>
                                <th class="pb-3 text-right font-black">
                                    Tạo lúc
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/30">
                            <tr
                                v-for="batch in batches.data"
                                :key="batch.id"
                                class="text-slate-700 transition-all hover:bg-muted/30 dark:text-slate-300"
                            >
                                <td class="py-3.5 pr-3">
                                    <p class="font-bold">{{ batch.name }}</p>
                                    <p
                                        v-if="batch.template_name"
                                        class="mt-0.5 text-[11px] text-muted-foreground"
                                    >
                                        Mẫu: {{ batch.template_name
                                        }}<span v-if="batch.template_season">
                                            · {{ batch.template_season }}</span
                                        >
                                    </p>
                                </td>
                                <td class="py-3.5 pr-3">
                                    <span
                                        class="rounded border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 font-mono text-xs font-black text-indigo-600 uppercase dark:text-indigo-400"
                                        >{{ batch.code_prefix }}</span
                                    >
                                </td>
                                <td
                                    class="py-3.5 pr-3 font-mono text-xs font-bold"
                                >
                                    {{
                                        batch.discount_type === 'percent'
                                            ? `${batch.discount_value}%`
                                            : `${batch.discount_value.toLocaleString('vi')}₫`
                                    }}
                                </td>
                                <td class="min-w-36 py-3.5 pr-3">
                                    <ProgressBar
                                        :value="batch.generated_count"
                                        :max="batch.code_count"
                                        show-label
                                    />
                                    <p
                                        class="mt-1 font-mono text-[10px] text-muted-foreground"
                                    >
                                        {{ batch.generated_count }} /
                                        {{ batch.code_count }} mã
                                    </p>
                                </td>
                                <td
                                    class="py-3.5 pr-3 font-mono text-xs text-slate-500"
                                >
                                    <span
                                        v-if="
                                            batch.starts_at || batch.expires_at
                                        "
                                        >{{ batch.starts_at ?? '—' }} →
                                        {{ batch.expires_at ?? '∞' }}</span
                                    >
                                    <span
                                        v-else
                                        class="font-medium text-muted-foreground/60 italic"
                                        >Không giới hạn</span
                                    >
                                </td>
                                <td class="py-3.5 pr-3">
                                    <div class="flex items-center gap-1.5">
                                        <StatusBadge
                                            :status="
                                                statusColorKey[batch.status]
                                            "
                                            >{{
                                                statusLabel[batch.status]
                                            }}</StatusBadge
                                        >
                                        <span
                                            v-if="batch.has_qr_sheet"
                                            title="Đã có QR sheet"
                                            class="text-emerald-500"
                                        >
                                            <QrCode class="size-3.5" />
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="py-3.5 text-right font-mono text-xs text-slate-500"
                                >
                                    {{ batch.created_at }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="batches.links" class="-mx-6 mt-2 -mb-4" />
            </CardContent>
        </Card>
    </div>

    <!-- Create batch slide-over -->
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <div
            v-if="showForm"
            class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-black/60 p-4 backdrop-blur-xs duration-300 fade-in"
            @click.self="closeForm"
        >
            <div
                class="flex w-full max-w-md flex-col justify-between overflow-hidden rounded-2xl border border-border/80 bg-background shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-border/40 bg-muted/10 p-5"
                >
                    <h2 class="flex items-center gap-2 text-sm font-bold">
                        <div
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg border border-orange-500/20 bg-orange-500/10 text-orange-500"
                        >
                            <Layers class="size-4" />
                        </div>
                        <span>Tạo lô coupon mới</span>
                    </h2>
                    <button
                        class="cursor-pointer rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-muted"
                        @click="closeForm"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="max-h-[70vh] space-y-4.5 overflow-y-auto p-5">
                    <div class="grid gap-1.5">
                        <Label
                            for="name"
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Tag class="size-3.5 text-orange-500" /> Tên lô
                            <span class="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="name"
                            v-model="formData.name"
                            placeholder="VD: Chiến dịch Tết 2027 - Đối tác"
                            class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        />
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="code_prefix"
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Hash class="size-3.5 text-orange-500" /> Tiền tố mã
                            <span class="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="code_prefix"
                            v-model="formData.code_prefix"
                            placeholder="VD: TET27"
                            class="rounded-xl border-border font-mono uppercase focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        />
                        <p
                            class="text-[10px] font-semibold text-muted-foreground"
                        >
                            Mỗi mã sinh ra sẽ có dạng
                            {{ formData.code_prefix || 'PREFIX' }}-XXXXXXXX.
                        </p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="code_count"
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Layers class="size-3.5 text-orange-500" /> Số lượng
                            mã <span class="text-rose-500">*</span>
                        </Label>
                        <Input
                            id="code_count"
                            v-model.number="formData.code_count"
                            type="number"
                            min="1"
                            max="1000"
                            class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Loại giảm giá</Label
                            >
                            <Select v-model="formData.discount_type">
                                <SelectTrigger
                                    class="h-9 rounded-xl border-border text-xs focus:border-orange-500 focus:ring-orange-500/20"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent class="rounded-xl">
                                    <SelectItem value="percent"
                                        >Phần trăm (%)</SelectItem
                                    >
                                    <SelectItem value="fixed"
                                        >Số tiền cố định (đ)</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="discount_value"
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >
                                <Coins class="size-3.5 text-orange-500" /> Giá
                                trị
                            </Label>
                            <Input
                                id="discount_value"
                                v-model.number="formData.discount_value"
                                type="number"
                                min="0"
                                :max="
                                    formData.discount_type === 'percent'
                                        ? 100
                                        : undefined
                                "
                                class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                            />
                        </div>
                    </div>

                    <div class="grid gap-1.5">
                        <Label
                            for="max_uses_per_code"
                            class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            <Users class="size-3.5 text-orange-500" /> Giới hạn
                            dùng / mã
                        </Label>
                        <Input
                            id="max_uses_per_code"
                            v-model.number="formData.max_uses_per_code"
                            type="number"
                            min="1"
                            class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label
                                for="starts_at"
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Ngày bắt đầu</Label
                            >
                            <Input
                                id="starts_at"
                                v-model="formData.starts_at"
                                type="date"
                                class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label
                                for="expires_at"
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Ngày hết hạn</Label
                            >
                            <Input
                                id="expires_at"
                                v-model="formData.expires_at"
                                type="date"
                                class="rounded-xl border-border focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="flex gap-3 border-t border-border/40 bg-muted/10 p-5"
                >
                    <Button
                        variant="outline"
                        class="flex-grow cursor-pointer rounded-xl border-border py-5 text-xs font-bold tracking-wider uppercase"
                        @click="closeForm"
                        >Hủy</Button
                    >
                    <Button
                        class="flex-grow cursor-pointer rounded-xl border-none bg-gradient-to-r from-orange-500 to-amber-500 py-5 text-xs font-bold tracking-wider text-white uppercase shadow-md transition-all hover:from-orange-600 hover:to-amber-600 hover:shadow-lg"
                        @click="submitForm"
                    >
                        <Check class="mr-1.5 size-4" /> Bắt đầu tạo
                    </Button>
                </div>
            </div>
        </div>
    </Transition>
</template>
