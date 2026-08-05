<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Database, HardDrive, Trash2, FileQuestion, ChevronLeft, ChevronRight, RefreshCw, AlertCircle, CheckCircle2, AlertTriangle } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { PageHeader } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface OrphanFile {
    id: number;
    file_name: string;
    file_path: string;
    file_url: string;
    disk: string;
    media_type: string;
    mime_type: string;
    size_bytes: number;
    size_mb: number;
    attachable_type: string | null;
    attachable_id: number | null;
    restaurant_name: string;
    restaurant_code: string | null;
    created_at: string;
}

const props = defineProps<{
    orphans: {
        data: OrphanFile[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        total_count: number;
        total_mb: number;
        default_disk: string;
    };
}>();

const selectedIds = ref<number[]>([]);
const processing = ref(false);

const isAllSelected = computed(() => {
    return (
        props.orphans.data.length > 0 &&
        props.orphans.data.every((o) => selectedIds.value.includes(o.id))
    );
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        // Deselect all on current page
        const currentPageIds = props.orphans.data.map((o) => o.id);
        selectedIds.value = selectedIds.value.filter(
            (id) => !currentPageIds.includes(id),
        );
    } else {
        // Select all on current page
        props.orphans.data.forEach((o) => {
            if (!selectedIds.value.includes(o.id)) {
                selectedIds.value.push(o.id);
            }
        });
    }
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((i) => i !== id);
    } else {
        selectedIds.value.push(id);
    }
}

async function cleanupSelected() {
    if (selectedIds.value.length === 0) {
        return;
    }

    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn xóa vĩnh viễn ${selectedIds.value.length} tệp đã chọn để giải phóng bộ nhớ? Thao tác này không thể hoàn tác.`,
        })
    ) {
        processing.value = true;
        router.post(
            '/super-admin/garbage-collector/cleanup',
            {
                ids: selectedIds.value,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = [];
                    processing.value = false;
                },
                onError: () => {
                    processing.value = false;
                },
            },
        );
    }
}

async function cleanupAll() {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'CẢNH BÁO: Bạn có chắc chắn muốn xóa TOÀN BỘ tệp mồ côi trong hệ thống? Thao tác này sẽ xóa vĩnh viễn tất cả tệp không còn liên kết để giải phóng dung lượng.',
        })
    ) {
        processing.value = true;
        router.post(
            '/super-admin/garbage-collector/cleanup',
            {
                all: true,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = [];
                    processing.value = false;
                },
                onError: () => {
                    processing.value = false;
                },
            },
        );
    }
}

async function deleteSingle(item: OrphanFile) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa vĩnh viễn tệp "${item.file_name}"?`,
        })
    ) {
        processing.value = true;
        router.post(
            '/super-admin/garbage-collector/cleanup',
            {
                ids: [item.id],
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedIds.value = selectedIds.value.filter(
                        (id) => id !== item.id,
                    );
                    processing.value = false;
                },
                onError: () => {
                    processing.value = false;
                },
            },
        );
    }
}

function navigatePage(page: number) {
    router.get(
        '/super-admin/garbage-collector',
        { page },
        { preserveState: true },
    );
}

function getAttachableLabel(type: string | null) {
    if (!type) {
        return 'Không có liên kết';
    }

    const parts = type.split('\\');

    return parts[parts.length - 1];
}

const storageAdvice = computed(() => {
    if (props.stats.total_count === 0) {
        return {
            status: 'perfect',
            label: 'Tối ưu',
            color: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20',
            desc: 'Không phát hiện bất kỳ tệp mồ côi nào trên hệ thống lưu trữ.',
        };
    }

    if (props.stats.total_mb > 50) {
        return {
            status: 'warning',
            label: 'Cần giải phóng',
            color: 'text-rose-500 bg-rose-500/10 border-rose-500/20',
            desc: `Phát hiện ${props.stats.total_count} tệp mồ côi chiếm ${props.stats.total_mb} MB dung lượng lãng phí.`,
        };
    }

    return {
        status: 'caution',
        label: 'Khá tốt',
        color: 'text-amber-500 bg-amber-500/10 border-amber-500/20',
        desc: `Có ${props.stats.total_count} tệp mồ côi chiếm ít dung lượng (${props.stats.total_mb} MB).`,
    };
});

const imageFilesCount = computed(() => {
    return props.orphans.data.filter((o) => o.media_type === 'image').length;
});

const otherFilesCount = computed(() => {
    return props.orphans.data.length - imageFilesCount.value;
});

const imageRatio = computed(() => {
    if (!props.orphans.data.length) {
        return 0;
    }

    return Math.round(
        (imageFilesCount.value / props.orphans.data.length) * 100,
    );
});

const diskRecommendation = computed(() => {
    if (props.stats.default_disk === 'local') {
        return {
            status: 'caution',
            text: 'Storage đang lưu cục bộ. Hãy cân nhắc cấu hình Driver S3 trong production để tăng tính ổn định.',
        };
    }

    return {
        status: 'ok',
        text: `Đang lưu trên bộ lưu trữ đám mây (${props.stats.default_disk.toUpperCase()}).`,
    };
});

const fileCleanupRecommendation = computed(() => {
    if (props.stats.total_count > 0) {
        return {
            status: 'warning',
            text: 'Nên xóa định kỳ các tệp mồ côi để tránh làm đầy các bản sao lưu database & file hệ thống.',
        };
    }

    return {
        status: 'ok',
        text: 'Hệ thống lưu trữ sạch sẽ, không cần can thiệp dọn dẹp.',
    };
});
</script>

<template>
    <Head title="Dọn dẹp tài nguyên & Rác" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Dọn dẹp tài nguyên & Hạn ngạch"
            subtitle="Quét và xóa bỏ các tệp mồ côi không còn liên kết trong CSDL để tối ưu dung lượng."
            :icon="Trash2"
        >
            <template #actions>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 cursor-pointer gap-1.5 rounded-xl border-border text-xs font-bold hover:bg-muted"
                    @click="router.reload({ preserveScroll: true })"
                    :disabled="processing"
                >
                    <RefreshCw
                        class="size-3.5"
                        :class="{ 'animate-spin': processing }"
                    />
                    Quét lại
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    class="h-9 cursor-pointer gap-1.5 rounded-xl bg-gradient-to-r from-rose-500 to-red-600 text-xs font-bold text-white shadow-xs hover:from-rose-600 hover:to-red-700"
                    @click="cleanupAll"
                    :disabled="stats.total_count === 0 || processing"
                >
                    <Trash2 class="size-3.5" /> Dọn sạch toàn bộ ({{
                        stats.total_count
                    }}
                    tệp)
                </Button>
            </template>
        </PageHeader>

        <!-- Stats Overview Widgets -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >Tổng số tệp mồ côi</CardTitle
                    >
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span
                            class="font-mono text-3xl font-bold text-slate-800 dark:text-slate-200"
                            >{{ stats.total_count }}</span
                        >
                        <span
                            class="text-xs font-semibold text-muted-foreground"
                            >tệp cần dọn dẹp</span
                        >
                    </div>
                    <Database
                        class="pointer-events-none absolute right-4 bottom-4 size-12 text-orange-500/10"
                    />
                </CardContent>
            </Card>

            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >Dung lượng lãng phí</CardTitle
                    >
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span
                            class="font-mono text-3xl font-bold text-rose-500"
                            >{{ stats.total_mb }}</span
                        >
                        <span
                            class="text-xs font-semibold text-muted-foreground"
                            >MB bộ nhớ</span
                        >
                    </div>
                    <HardDrive
                        class="pointer-events-none absolute right-4 bottom-4 size-12 text-rose-500/10"
                    />
                </CardContent>
            </Card>

            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >Storage Driver mặc định</CardTitle
                    >
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span
                            class="font-mono text-2xl font-bold text-slate-800 uppercase dark:text-slate-200"
                            >{{ stats.default_disk }}</span
                        >
                    </div>
                    <span
                        class="text-[10px] font-semibold text-muted-foreground"
                        >Cấu hình từ filesystems.php</span
                    >
                </CardContent>
            </Card>
        </div>

        <!-- Warning Alert Banner -->
        <div
            v-if="stats.total_count > 0"
            class="flex items-start gap-3 rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-4 text-amber-800 backdrop-blur-md dark:text-amber-300"
        >
            <AlertCircle class="mt-0.5 size-5 shrink-0 text-amber-500" />
            <div>
                <h4 class="text-xs font-bold tracking-wider uppercase">
                    Cảnh báo bảo tồn dữ liệu
                </h4>
                <p class="mt-1 text-xs leading-relaxed font-medium">
                    Các tệp mồ côi dưới đây là tệp tin đã tải lên hệ thống nhưng
                    không còn liên kết với sản phẩm, món ăn, bài viết hoặc tài
                    khoản nào (thường do thao tác xóa món ăn hoặc cập nhật hình
                    ảnh lỗi). Khi nhấn xóa vĩnh viễn, tệp vật lý tương ứng trên
                    ổ đĩa / S3 Cloud sẽ được gỡ bỏ hoàn toàn.
                </p>
            </div>
        </div>

        <!-- Clean up Actions Bar -->
        <div
            v-if="selectedIds.length > 0"
            class="flex items-center justify-between rounded-2xl border border-orange-500/20 bg-orange-500/5 px-4 py-3 shadow-xs backdrop-blur-md"
        >
            <span class="text-slate-850 text-xs font-bold dark:text-slate-100">
                Đã chọn
                <span class="font-mono font-black text-orange-500">{{
                    selectedIds.length
                }}</span>
                tệp mồ côi
            </span>
            <Button
                variant="destructive"
                size="sm"
                class="h-9 cursor-pointer gap-1.5 rounded-xl bg-rose-600 text-xs font-bold shadow-md hover:bg-rose-700"
                @click="cleanupSelected"
                :disabled="processing"
            >
                <Trash2 class="size-3.5" /> Xóa các mục đã chọn
            </Button>
        </div>

        <!-- Workspace Layout -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Left Column: Orphans Table (8 columns) -->
            <div class="flex flex-col gap-6 lg:col-span-8">
                <!-- Orphans Table Card -->
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader
                        class="border-b border-border/40 bg-muted/10 p-5"
                    >
                        <CardTitle
                            class="text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                            >Danh sách tệp mồ côi</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="orphans.data.length === 0"
                            class="flex flex-col items-center justify-center py-20 text-muted-foreground/60"
                        >
                            <div class="mb-3 rounded-full bg-muted p-4">
                                <Database class="h-10 w-10 text-emerald-500" />
                            </div>
                            <h3
                                class="text-xs font-bold tracking-wider text-foreground uppercase"
                            >
                                Hệ thống sạch sẽ!
                            </h3>
                            <p
                                class="mt-1 max-w-xs text-center text-[10px] font-semibold text-muted-foreground"
                            >
                                Không tìm thấy tệp mồ côi nào trong hệ thống.
                                Tuyệt vời!
                            </p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-border/45 bg-muted/20 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        <th
                                            class="w-12 px-5 py-3.5 text-center"
                                        >
                                            <input
                                                type="checkbox"
                                                :checked="isAllSelected"
                                                @change="toggleSelectAll"
                                                class="size-4 cursor-pointer rounded border-border accent-orange-500"
                                            />
                                        </th>
                                        <th class="px-4 py-3.5">
                                            Xem trước & Tên tệp
                                        </th>
                                        <th class="px-4 py-3.5">
                                            Nhà hàng (Tenant)
                                        </th>
                                        <th class="px-4 py-3.5">
                                            Loại thực thể
                                        </th>
                                        <th class="px-4 py-3.5">Kích thước</th>
                                        <th class="px-4 py-3.5">
                                            Ngày tải lên
                                        </th>
                                        <th class="px-5 py-3.5 text-right">
                                            Thao tác
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/25">
                                    <tr
                                        v-for="item in orphans.data"
                                        :key="item.id"
                                        class="group transition-colors hover:bg-muted/15"
                                    >
                                        <td class="px-5 py-3 text-center">
                                            <input
                                                type="checkbox"
                                                :checked="
                                                    selectedIds.includes(
                                                        item.id,
                                                    )
                                                "
                                                @change="toggleSelect(item.id)"
                                                class="size-4 cursor-pointer rounded border-border accent-orange-500"
                                            />
                                        </td>
                                        <td
                                            class="flex items-center gap-3 px-4 py-3"
                                        >
                                            <div
                                                class="flex h-10 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border/80 bg-muted"
                                            >
                                                <img
                                                    v-if="
                                                        item.media_type ===
                                                        'image'
                                                    "
                                                    :src="item.file_url"
                                                    class="h-full w-full object-cover"
                                                    :alt="item.file_name"
                                                    @error="
                                                        (e: any) =>
                                                            (e.target.style.display =
                                                                'none')
                                                    "
                                                />
                                                <FileQuestion
                                                    v-else
                                                    class="size-5 text-muted-foreground"
                                                />
                                            </div>
                                            <div
                                                class="max-w-xs min-w-0 md:max-w-md"
                                            >
                                                <p
                                                    class="dark:text-slate-350 truncate text-xs font-bold text-slate-700"
                                                    :title="item.file_name"
                                                >
                                                    {{ item.file_name }}
                                                </p>
                                                <p
                                                    class="mt-0.5 truncate font-mono text-[9px] font-semibold text-muted-foreground"
                                                    :title="item.file_path"
                                                >
                                                    {{
                                                        item.disk.toUpperCase()
                                                    }}
                                                    · {{ item.file_path }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                v-if="item.restaurant_code"
                                                class="inline-flex flex-col"
                                            >
                                                <span
                                                    class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                                    >{{
                                                        item.restaurant_name
                                                    }}</span
                                                >
                                                <span
                                                    class="mt-0.5 font-mono text-[9px] font-semibold text-muted-foreground"
                                                    >{{
                                                        item.restaurant_code
                                                    }}</span
                                                >
                                            </span>
                                            <span
                                                v-else
                                                class="text-[10px] font-bold text-muted-foreground"
                                                >Hệ thống (Global)</span
                                            >
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="text-orange-650 inline-flex items-center rounded-lg border border-orange-500/15 bg-orange-500/10 px-2 py-0.5 text-[9px] font-bold dark:text-orange-400"
                                            >
                                                {{
                                                    getAttachableLabel(
                                                        item.attachable_type,
                                                    )
                                                }}
                                                <span
                                                    v-if="item.attachable_id"
                                                    class="ml-1 font-mono font-bold"
                                                    >#{{
                                                        item.attachable_id
                                                    }}</span
                                                >
                                            </span>
                                        </td>
                                        <td
                                            class="dark:text-slate-350 px-4 py-3 font-mono font-bold text-slate-700"
                                        >
                                            {{ item.size_mb }} MB
                                        </td>
                                        <td
                                            class="px-4 py-3 text-[10px] font-semibold text-muted-foreground"
                                        >
                                            {{ item.created_at }}
                                        </td>
                                        <td class="px-5 py-3 text-right">
                                            <button
                                                @click="deleteSingle(item)"
                                                class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-rose-500 opacity-0 transition-colors group-hover:opacity-100 hover:bg-rose-500/10"
                                                title="Xóa vĩnh viễn tệp này"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div
                            v-if="orphans.last_page > 1"
                            class="flex items-center justify-between border-t border-border/20 bg-muted/10 px-6 py-4"
                        >
                            <span
                                class="text-[10px] font-semibold text-muted-foreground"
                            >
                                Trang {{ orphans.current_page }} /
                                {{ orphans.last_page }} · Tổng
                                {{ orphans.total }} tệp mồ côi
                            </span>
                            <div class="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="orphans.current_page <= 1"
                                    @click="
                                        navigatePage(orphans.current_page - 1)
                                    "
                                    class="cursor-pointer rounded-lg text-xs font-bold"
                                >
                                    <ChevronLeft class="mr-1 size-4" /> Trước
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        orphans.current_page >=
                                        orphans.last_page
                                    "
                                    @click="
                                        navigatePage(orphans.current_page + 1)
                                    "
                                    class="cursor-pointer rounded-lg text-xs font-bold"
                                >
                                    Sau <ChevronRight class="ml-1 size-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Column: AI Storage Advisor (4 columns) -->
            <div class="flex flex-col gap-6 lg:col-span-4">
                <!-- AI Storage Coach Card -->
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardContent class="space-y-4 p-5">
                        <div
                            class="flex items-center justify-between border-b border-border/40 pb-3"
                        >
                            <h4
                                class="text-xs font-black tracking-wider text-muted-foreground uppercase"
                            >
                                AI Storage Coach
                            </h4>
                            <span
                                class="animate-pulse rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                :class="
                                    stats.total_count > 0
                                        ? 'border-amber-500/20 bg-amber-500/10 text-amber-600'
                                        : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                "
                            >
                                {{
                                    stats.total_count > 0
                                        ? 'Cần Dọn Dẹp'
                                        : 'Tối Ưu'
                                }}
                            </span>
                        </div>

                        <!-- Score Rank Layout -->
                        <div
                            class="flex items-center gap-4 rounded-2xl border border-border/30 bg-muted/15 p-4"
                        >
                            <div
                                class="flex size-14 shrink-0 items-center justify-center rounded-xl border text-xl font-black shadow-xs"
                                :class="storageAdvice.color"
                            >
                                {{
                                    stats.total_count === 0
                                        ? 'A+'
                                        : stats.total_mb > 100
                                          ? 'D'
                                          : 'B'
                                }}
                            </div>
                            <div>
                                <h5
                                    class="text-xs font-black text-slate-800 dark:text-slate-200"
                                >
                                    Xếp hạng: {{ storageAdvice.label }}
                                </h5>
                                <p
                                    class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                >
                                    {{ storageAdvice.desc }}
                                </p>
                            </div>
                        </div>

                        <!-- File Type Classification Progress Bar -->
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-[10px] font-bold text-muted-foreground"
                            >
                                <span>Phân loại định dạng tệp mồ côi</span>
                                <span>{{ imageRatio }}% Hình ảnh</span>
                            </div>
                            <!-- Progress Bar -->
                            <div
                                class="flex h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
                            >
                                <div
                                    class="h-full bg-orange-500"
                                    :style="{ width: imageRatio + '%' }"
                                    title="Hình ảnh mồ côi"
                                ></div>
                                <div
                                    class="h-full flex-1 bg-slate-500"
                                    title="Tài liệu & định dạng khác"
                                ></div>
                            </div>
                            <div
                                class="flex items-center justify-between text-[9px] font-semibold text-muted-foreground"
                            >
                                <span class="flex items-center gap-1"
                                    ><span
                                        class="block size-2 rounded-full bg-orange-500"
                                    ></span>
                                    Hình ảnh: {{ imageFilesCount }} tệp</span
                                >
                                <span class="flex items-center gap-1"
                                    ><span
                                        class="block size-2 rounded-full bg-slate-500"
                                    ></span>
                                    Khác: {{ otherFilesCount }} tệp</span
                                >
                            </div>
                        </div>

                        <!-- Diagnostics recommendations -->
                        <div class="space-y-2.5 border-t border-border/40 pt-3">
                            <h5
                                class="dark:text-slate-350 text-[10px] font-black tracking-wider text-slate-700 uppercase"
                            >
                                Khuyến nghị chẩn đoán:
                            </h5>

                            <!-- Advice 1 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div
                                    class="mt-0.5 shrink-0"
                                    :class="
                                        diskRecommendation.status === 'caution'
                                            ? 'text-amber-500'
                                            : 'text-emerald-500'
                                    "
                                >
                                    <AlertCircle
                                        v-if="
                                            diskRecommendation.status ===
                                            'caution'
                                        "
                                        class="size-3.5"
                                    />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p
                                    class="leading-normal font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    {{ diskRecommendation.text }}
                                </p>
                            </div>

                            <!-- Advice 2 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div
                                    class="mt-0.5 shrink-0"
                                    :class="
                                        fileCleanupRecommendation.status ===
                                        'warning'
                                            ? 'text-amber-500'
                                            : 'text-emerald-500'
                                    "
                                >
                                    <AlertTriangle
                                        v-if="
                                            fileCleanupRecommendation.status ===
                                            'warning'
                                        "
                                        class="size-3.5"
                                    />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p
                                    class="leading-normal font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    {{ fileCleanupRecommendation.text }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
