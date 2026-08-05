<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Search,
    Database,
    Activity,
    RotateCw,
    Play,
    Trash2,
    Clock,
    AlertCircle,
    CheckCircle2,
    Sliders,
    Sparkles,
    TrendingUp,
    AlertTriangle,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { PageHeader } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

// Layout config
defineOptions({ layout: AppLayout });

interface IndexSyncStatus {
    status: 'idle' | 'pending' | 'processing' | 'success' | 'failed';
    action: 'import' | 'flush' | null;
    completed_at: string | null;
    failed_at: string | null;
    error: string | null;
}

interface IndexStat {
    index_name: string;
    label: string;
    model: string;
    documents_count: number;
    db_records_count: number;
    is_indexing: boolean;
    sync_status: IndexSyncStatus;
    out_of_sync: boolean;
}

interface ConnectionInfo {
    online: boolean;
    host: string;
    driver: string;
    database_size: number;
    error: string | null;
}

interface LatencyStat {
    index_name: string;
    avg_latency: number;
    total_count: number;
}

interface KeywordStat {
    rank: number;
    keyword: string;
    index_name: string;
    search_count: number;
    avg_latency: number;
}

interface RecentSearch {
    keyword: string;
    index_name: string;
    latency_ms: number;
    created_at: string;
}

interface StatisticsInfo {
    total_searches: number;
    average_latency: number;
    latency_by_index: LatencyStat[];
    top_keywords: KeywordStat[];
    latest_searches: RecentSearch[];
}

const props = defineProps<{
    connection: ConnectionInfo;
    indexes: IndexStat[];
    statistics: StatisticsInfo;
}>();

const localIndexes = ref<IndexStat[]>([...props.indexes]);
const isSyncing = ref<Record<string, boolean>>({});
const isClearingStats = ref(false);

function formatBytes(bytes: number, decimals = 2) {
    if (bytes === 0) {
        return '0 Bytes';
    }

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

async function triggerSync(indexName: string, action: 'import' | 'flush') {
    const key = `${indexName}_${action}`;
    isSyncing.value[key] = true;

    const promise = fetch('/super-admin/meilisearch-console/sync', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN':
                (
                    document.querySelector(
                        'meta[name="csrf-token"]',
                    ) as HTMLMetaElement | null
                )?.content || '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ index_name: indexName, action }),
    }).then(async (res) => {
        if (!res.ok) {
            throw new Error('Yêu cầu thất bại');
        }

        const data = await res.json();

        if (data.success) {
            // Update local state sync status to pending
            const targetIndex = localIndexes.value.find(
                (idx) => idx.index_name === indexName,
            );

            if (targetIndex) {
                targetIndex.sync_status = data.sync_status;
            }

            return (
                data.message || 'Lệnh đồng bộ chỉ mục đã được đưa vào hàng đợi'
            );
        }

        throw new Error(data.message || 'Lỗi không xác định');
    });

    toast.promise(promise, {
        loading: `Đang gửi lệnh ${action === 'import' ? 'Đồng bộ' : 'Xóa sạch'} đến hàng đợi...`,
        success: (msg: any) => msg,
        error: (err: any) => `Lỗi: ${err.message || err}`,
    });

    promise.finally(() => {
        isSyncing.value[key] = false;
        // Reload page data in background after 2 seconds to get processing status
        setTimeout(() => {
            router.reload({ only: ['indexes'] });
        }, 1500);
    });
}

async function clearSearchStatistics() {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn muốn xóa toàn bộ lịch sử và số liệu thống kê tìm kiếm?',
        }))
    ) {
        return;
    }

    isClearingStats.value = true;
    router.post(
        '/super-admin/meilisearch-console/clear-stats',
        {},
        {
            onSuccess: () => {
                toast.success('Đã xóa dữ liệu thống kê thành công.');
                isClearingStats.value = false;
            },
            onError: () => {
                toast.error('Không thể xóa dữ liệu thống kê.');
                isClearingStats.value = false;
            },
        },
    );
}

function getSyncBadgeClass(status: string) {
    switch (status) {
        case 'pending':
            return 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 animate-pulse border-zinc-200 dark:border-zinc-700';
        case 'processing':
            return 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 animate-pulse border-indigo-100 dark:border-indigo-900/50';
        case 'success':
            return 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-300 border-green-100 dark:border-green-900/30';
        case 'failed':
            return 'bg-red-50 text-red-700 dark:bg-rose-950/40 dark:text-rose-400 border-red-100 dark:border-rose-900/30';
        default:
            return 'bg-muted text-muted-foreground border-border';
    }
}

function getSyncStatusLabel(status: string) {
    switch (status) {
        case 'pending':
            return 'Đang chờ';
        case 'processing':
            return 'Đang xử lý';
        case 'success':
            return 'Thành công';
        case 'failed':
            return 'Thất bại';
        default:
            return 'Sẵn sàng';
    }
}

function getIndexVisualPercentage(idx: IndexStat) {
    if (idx.db_records_count === 0 && idx.documents_count === 0) {
        return 100;
    }

    if (idx.db_records_count === 0) {
        return 0;
    }

    const ratio = (idx.documents_count / idx.db_records_count) * 100;

    return Math.min(Math.round(ratio), 100);
}

const overallSyncPercentage = computed(() => {
    let totalDB = 0;
    let totalIndexed = 0;
    props.indexes.forEach((idx) => {
        totalDB += idx.db_records_count;
        totalIndexed += idx.documents_count;
    });

    if (totalDB === 0) {
        return 100;
    }

    return Math.min(Math.round((totalIndexed / totalDB) * 100), 100);
});

const searchHealthGrade = computed(() => {
    if (!props.connection.online) {
        return {
            grade: 'OFFLINE',
            color: 'text-rose-500 bg-rose-500/10 border-rose-500/20',
            label: 'Mất kết nối',
            desc: 'Máy chủ Meilisearch đang ngoại tuyến hoặc cấu hình sai Host/Port.',
        };
    }

    const outOfSyncCount = props.indexes.filter(
        (idx) => idx.out_of_sync,
    ).length;

    if (props.connection.driver !== 'meilisearch') {
        return {
            grade: 'C',
            color: 'text-amber-600 bg-amber-500/10 border-amber-500/20',
            label: 'Cấu hình sai Driver',
            desc: 'Máy chủ Meilisearch đang trực tuyến nhưng trình điều khiển Laravel Scout chưa được thiết lập là meilisearch.',
        };
    }

    if (outOfSyncCount > 0) {
        if (overallSyncPercentage.value < 70) {
            return {
                grade: 'C',
                color: 'text-rose-500 bg-rose-500/10 border-rose-500/20',
                label: 'Mất đồng bộ nghiêm trọng',
                desc: `Phát hiện ${outOfSyncCount} chỉ mục có chênh lệch bản ghi lớn (Tỷ lệ khớp < 70%).`,
            };
        }

        return {
            grade: 'B',
            color: 'text-amber-500 bg-amber-500/10 border-amber-500/20',
            label: 'Chưa đồng bộ đầy đủ',
            desc: `Phát hiện ${outOfSyncCount} chỉ mục chưa được đồng bộ hoàn toàn với Database.`,
        };
    }

    if (props.statistics.average_latency > 50) {
        return {
            grade: 'A',
            color: 'text-cyan-500 bg-cyan-500/10 border-cyan-500/20',
            label: 'Hoạt động - Độ trễ cao',
            desc: 'Chỉ mục đồng bộ đầy đủ nhưng độ trễ tìm kiếm trung bình còn cao (>50ms).',
        };
    }

    return {
        grade: 'A+',
        color: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20',
        label: 'Tối ưu hoàn hảo',
        desc: 'Hệ thống tìm kiếm đang trực tuyến, trình điều khiển Scout chính xác và 100% chỉ mục đã khớp.',
    };
});

const connectionAdvice = computed(() => {
    if (!props.connection.online) {
        return {
            status: 'error',
            text: 'Meilisearch offline. Cần chạy dịch vụ meilisearch.exe hoặc kiểm tra cổng 7700 trên server.',
        };
    }

    return {
        status: 'ok',
        text: `Kết nối máy chủ Meilisearch trên ${props.connection.host} ổn định.`,
    };
});

const driverAdvice = computed(() => {
    if (props.connection.driver !== 'meilisearch') {
        return {
            status: 'warning',
            text: `Trình điều khiển Scout đang là '${props.connection.driver}'. Cần đổi SCOUT_DRIVER=meilisearch trong tệp .env.`,
        };
    }

    return {
        status: 'ok',
        text: 'Trình điều khiển Scout được cấu hình chính xác.',
    };
});

const indexSyncAdvice = computed(() => {
    const outOfSyncCount = props.indexes.filter(
        (idx) => idx.out_of_sync,
    ).length;

    if (outOfSyncCount > 0) {
        return {
            status: 'warning',
            text: `Có ${outOfSyncCount} chỉ mục bị lệch. Hãy bấm nút "Đồng bộ" tương ứng để đồng bộ.`,
        };
    }

    return {
        status: 'ok',
        text: 'Tất cả chỉ mục tìm kiếm khớp hoàn toàn với CSDL chính.',
    };
});

const latencyAdvice = computed(() => {
    if (props.statistics.average_latency > 50) {
        return {
            status: 'warning',
            text: 'Độ trễ trung bình cao (> 50ms). Khuyên lọc bớt searchableAttributes.',
        };
    }

    return { status: 'ok', text: 'Độ trễ truy vấn ở mức tối ưu.' };
});
</script>

<template>
    <Head title="DevOps - Bảng điều khiển Meilisearch" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Bảng điều khiển Meilisearch"
            subtitle="Quản lý các chỉ mục Laravel Scout, theo dõi độ trễ tìm kiếm và thống kê hiệu năng."
            :icon="Database"
        >
            <template #actions>
                <Button
                    @click="router.reload()"
                    variant="outline"
                    class="h-9 cursor-pointer gap-1.5 rounded-xl border-border text-xs font-bold hover:bg-muted"
                >
                    <RotateCw class="size-3.5" />
                    Làm mới dữ liệu
                </Button>
            </template>
        </PageHeader>

        <!-- Connection State & Summary Stats -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Connection Status -->
            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        :class="[
                            'shrink-0 rounded-xl p-2.5',
                            props.connection.online
                                ? 'bg-emerald-500/10 text-emerald-500'
                                : 'bg-rose-500/10 text-rose-500',
                        ]"
                    >
                        <Activity class="size-5" />
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <span
                            class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >Kết nối máy chủ</span
                        >
                        <span
                            class="mt-0.5 flex items-center gap-1.5 text-lg font-bold tracking-tight"
                        >
                            {{
                                props.connection.online
                                    ? 'TRỰC TUYẾN'
                                    : 'NGOẠI TUYẾN'
                            }}
                            <span
                                :class="[
                                    'relative flex h-2 w-2',
                                    props.connection.online
                                        ? 'text-emerald-500'
                                        : 'text-rose-500',
                                ]"
                            >
                                <span
                                    :class="[
                                        'absolute inline-flex h-full w-full animate-ping rounded-full opacity-75',
                                        props.connection.online
                                            ? 'bg-emerald-400'
                                            : 'bg-rose-400',
                                    ]"
                                ></span>
                                <span
                                    :class="[
                                        'relative inline-flex h-2 w-2 rounded-full',
                                        props.connection.online
                                            ? 'bg-emerald-500'
                                            : 'bg-rose-500',
                                    ]"
                                ></span>
                            </span>
                        </span>
                        <span
                            class="mt-0.5 truncate font-mono text-[9px] font-semibold text-muted-foreground"
                        >
                            {{ props.connection.host }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Database Size -->
            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="shrink-0 rounded-xl bg-orange-500/10 p-2.5 text-orange-500"
                    >
                        <Database class="size-5" />
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <span
                            class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >Kích thước DB</span
                        >
                        <span class="mt-0.5 text-lg font-bold tracking-tight">
                            {{ formatBytes(props.connection.database_size) }}
                        </span>
                        <span
                            class="mt-0.5 text-[9px] font-semibold text-muted-foreground"
                        >
                            Dung lượng cache LMDB
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Search Volume -->
            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="shrink-0 rounded-xl bg-violet-500/10 p-2.5 text-violet-500"
                    >
                        <Search class="size-5" />
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <span
                            class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >Tổng lượt truy vấn</span
                        >
                        <span
                            class="mt-0.5 text-lg font-bold tracking-tight text-violet-500"
                        >
                            {{ props.statistics.total_searches }}
                        </span>
                        <span
                            class="mt-0.5 text-[9px] font-semibold text-muted-foreground"
                        >
                            Lượt tìm kiếm hệ thống
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Response Latency -->
            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="shrink-0 rounded-xl bg-cyan-500/10 p-2.5 text-cyan-500"
                    >
                        <Clock class="size-5" />
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <span
                            class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                            >Độ trễ trung bình</span
                        >
                        <span
                            class="mt-0.5 text-lg font-bold tracking-tight text-cyan-500"
                        >
                            {{ props.statistics.average_latency }}ms
                        </span>
                        <span
                            class="mt-0.5 text-[9px] font-semibold text-muted-foreground"
                        >
                            Thời gian phản hồi bình quân
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Global Index Sync Progress Bar Card -->
        <Card
            class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 p-5 shadow-2xs backdrop-blur-md"
        >
            <div
                class="mb-3 flex flex-col justify-between gap-4 sm:flex-row sm:items-center"
            >
                <div class="flex items-center gap-2">
                    <div class="rounded-xl bg-amber-500/10 p-2 text-amber-500">
                        <TrendingUp class="size-4" />
                    </div>
                    <div>
                        <h3
                            class="text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                        >
                            Tiến độ đồng bộ chỉ mục toàn cục
                        </h3>
                        <p
                            class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                        >
                            Tỉ lệ khớp tài liệu trong chỉ mục so với tổng số bản
                            ghi trong Cơ sở dữ liệu.
                        </p>
                    </div>
                </div>
                <div
                    class="flex shrink-0 items-baseline gap-1.5 rounded-xl border border-amber-500/20 bg-amber-500/10 px-3 py-1.5"
                >
                    <span
                        class="font-mono text-lg leading-none font-black text-amber-500"
                        >{{ overallSyncPercentage }}%</span
                    >
                    <span
                        class="text-[10px] leading-none font-semibold text-muted-foreground uppercase"
                        >Đã khớp</span
                    >
                </div>
            </div>

            <div
                class="flex h-3 w-full overflow-hidden rounded-full border border-border/40 bg-muted shadow-inner"
            >
                <div
                    class="relative h-full rounded-full bg-gradient-to-r from-orange-500 via-amber-500 to-yellow-500 shadow-sm transition-all duration-700"
                    :style="{ width: `${overallSyncPercentage}%` }"
                >
                    <!-- Glossy overlay for premium look -->
                    <div
                        class="absolute inset-0 bg-linear-to-b from-white/10 to-transparent"
                    ></div>
                </div>
            </div>

            <div
                class="mt-2.5 flex items-center justify-between font-mono text-[9px] font-semibold text-muted-foreground"
            >
                <span>0%</span>
                <span class="font-sans text-slate-700 dark:text-slate-300">
                    {{
                        overallSyncPercentage === 100
                            ? '✅ Toàn bộ chỉ mục đã khớp dữ liệu.'
                            : '⚠️ Có chỉ mục chưa đồng bộ, cần chạy tác vụ đồng bộ.'
                    }}
                </span>
                <span>100%</span>
            </div>
        </Card>

        <!-- Connection Error Alert if Offline -->
        <div
            v-if="!props.connection.online && props.connection.error"
            class="dark:text-rose-350 flex gap-3 rounded-2xl border border-rose-500/20 bg-rose-500/[0.04] p-4 text-xs text-rose-800 backdrop-blur-md"
        >
            <AlertCircle class="mt-0.5 size-5 shrink-0 text-rose-500" />
            <div class="space-y-1">
                <span
                    class="font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400"
                    >Lỗi kết nối tới Meilisearch</span
                >
                <p
                    class="dark:text-slate-350/80 font-mono text-xs leading-normal break-all text-slate-700"
                >
                    {{ props.connection.error }}
                </p>
                <p
                    class="mt-1.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                >
                    Vui lòng kiểm tra lại dịch vụ Meilisearch trên cổng `7700`
                    hoặc cập nhật biến môi trường `MEILISEARCH_HOST` trong file
                    `.env`.
                </p>
            </div>
        </div>

        <!-- Workspace Layout -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Left Side: Index Sync Tool & Performance Metrics (8 columns) -->
            <div class="flex flex-col gap-6 lg:col-span-8">
                <!-- Index Sync Tool Card -->
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader
                        class="flex flex-row flex-wrap items-center justify-between gap-4 border-b border-border/40 bg-muted/10 p-5"
                    >
                        <div>
                            <CardTitle
                                class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                            >
                                <Sliders class="size-5 text-orange-500" />
                                Danh sách và đồng bộ chỉ mục
                            </CardTitle>
                            <CardDescription
                                class="mt-1 text-xs font-semibold text-muted-foreground"
                            >
                                Kiểm tra chênh lệch số lượng bản ghi giữa cơ sở
                                dữ liệu gốc và chỉ mục tìm kiếm Meilisearch,
                                thực hiện đồng bộ lại chỉ mục.
                            </CardDescription>
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                                >Trình điều khiển Scout:</span
                            >
                            <span
                                class="rounded-lg border border-orange-500/15 bg-orange-500/10 px-2.5 py-0.5 font-mono text-[10px] font-black text-orange-600 uppercase dark:text-orange-400"
                            >
                                {{ props.connection.driver }}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            class="w-full overflow-x-auto lg:overflow-x-hidden"
                        >
                            <table
                                class="w-full table-fixed border-collapse text-left text-xs"
                            >
                                <colgroup>
                                    <col class="w-[20%]" />
                                    <col class="w-[18%]" />
                                    <col class="w-[8%]" />
                                    <col class="w-[8%]" />
                                    <col class="w-[11%]" />
                                    <col class="w-[11%]" />
                                    <col class="w-[24%]" />
                                </colgroup>
                                <thead>
                                    <tr
                                        class="border-b border-border/45 bg-muted/20 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >
                                        <th class="px-5 py-3.5">Chỉ mục</th>
                                        <th class="px-4 py-3.5">
                                            Model tương ứng
                                        </th>
                                        <th class="px-4 py-3.5 text-center">
                                            Bản ghi trong DB
                                        </th>
                                        <th class="px-4 py-3.5 text-center">
                                            Bản ghi trong chỉ mục
                                        </th>
                                        <th class="px-4 py-3.5 text-center">
                                            Tỷ lệ đồng bộ
                                        </th>
                                        <th class="px-4 py-3.5 text-center">
                                            Trạng thái cuối
                                        </th>
                                        <th class="px-5 py-3.5 text-right">
                                            Thao tác đồng bộ
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/25">
                                    <tr
                                        v-for="idx in localIndexes"
                                        :key="idx.index_name"
                                        class="group transition-colors hover:bg-muted/15"
                                    >
                                        <td class="min-w-0 px-5 py-3.5">
                                            <div
                                                class="flex min-w-0 flex-wrap items-center gap-2 font-bold text-slate-800 dark:text-slate-200"
                                            >
                                                <span
                                                    class="max-w-full break-words"
                                                    >{{ idx.label }}</span
                                                >
                                                <span
                                                    class="max-w-full rounded border border-border/40 bg-background px-1.5 py-0.5 font-mono text-[9px] font-bold break-all text-muted-foreground dark:bg-slate-900"
                                                >
                                                    {{ idx.index_name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td
                                            class="min-w-0 px-4 py-3.5 font-mono text-[10px] font-semibold break-all text-muted-foreground"
                                        >
                                            {{ idx.model }}
                                        </td>
                                        <td
                                            class="dark:text-slate-350 px-4 py-3.5 text-center font-mono font-bold text-slate-700"
                                        >
                                            {{ idx.db_records_count }}
                                        </td>
                                        <td
                                            class="px-4 py-3.5 text-center font-mono font-bold"
                                        >
                                            <span
                                                :class="
                                                    idx.out_of_sync
                                                        ? 'text-orange-500'
                                                        : 'dark:text-slate-350 text-slate-700'
                                                "
                                            >
                                                {{ idx.documents_count }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div
                                                class="mx-auto flex max-w-[120px] min-w-0 flex-col items-center gap-1"
                                            >
                                                <div
                                                    class="flex h-1.5 w-full overflow-hidden rounded-full border border-border/40 bg-muted"
                                                >
                                                    <div
                                                        :class="[
                                                            'h-full rounded-full transition-all duration-500',
                                                            idx.out_of_sync
                                                                ? 'bg-gradient-to-r from-orange-500 to-amber-500'
                                                                : 'bg-gradient-to-r from-amber-500 to-yellow-500',
                                                        ]"
                                                        :style="{
                                                            width: `${getIndexVisualPercentage(idx)}%`,
                                                        }"
                                                    ></div>
                                                </div>
                                                <span
                                                    class="font-mono text-[9px] font-bold text-muted-foreground"
                                                    >{{
                                                        getIndexVisualPercentage(
                                                            idx,
                                                        )
                                                    }}% khớp</span
                                                >
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5 text-center">
                                            <div
                                                class="flex min-w-0 flex-col items-center gap-0.5"
                                            >
                                                <span
                                                    :class="[
                                                        'inline-flex items-center gap-1 rounded-lg border px-2.5 py-0.5 text-[9px] font-bold',
                                                        getSyncBadgeClass(
                                                            idx.sync_status
                                                                .status,
                                                        ),
                                                    ]"
                                                >
                                                    {{
                                                        getSyncStatusLabel(
                                                            idx.sync_status
                                                                .status,
                                                        )
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        idx.sync_status
                                                            .completed_at
                                                    "
                                                    class="mt-0.5 text-[9px] font-semibold text-muted-foreground"
                                                >
                                                    {{
                                                        idx.sync_status
                                                            .action === 'import'
                                                            ? 'Đồng bộ'
                                                            : 'Xóa'
                                                    }}
                                                    {{
                                                        idx.sync_status.completed_at.split(
                                                            ' ',
                                                        )[1]
                                                    }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 pr-6 text-right">
                                            <div
                                                class="flex flex-col justify-end gap-2 xl:flex-row"
                                            >
                                                <!-- Flush Button -->
                                                <Button
                                                    @click="
                                                        triggerSync(
                                                            idx.index_name,
                                                            'flush',
                                                        )
                                                    "
                                                    :disabled="
                                                        isSyncing[
                                                            `${idx.index_name}_flush`
                                                        ] ||
                                                        isSyncing[
                                                            `${idx.index_name}_import`
                                                        ] ||
                                                        idx.is_indexing ||
                                                        !props.connection.online
                                                    "
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 w-full cursor-pointer rounded-xl border border-border text-xs font-bold text-rose-500 hover:bg-rose-500/10 hover:text-rose-600 xl:w-auto"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                    Xóa
                                                </Button>

                                                <!-- Import Button -->
                                                <Button
                                                    @click="
                                                        triggerSync(
                                                            idx.index_name,
                                                            'import',
                                                        )
                                                    "
                                                    :disabled="
                                                        isSyncing[
                                                            `${idx.index_name}_import`
                                                        ] ||
                                                        isSyncing[
                                                            `${idx.index_name}_flush`
                                                        ] ||
                                                        idx.is_indexing ||
                                                        !props.connection.online
                                                    "
                                                    size="sm"
                                                    class="h-8 w-full cursor-pointer gap-1 rounded-xl border-none bg-gradient-to-r from-orange-500 to-amber-500 text-xs font-bold text-white shadow-xs hover:from-orange-600 hover:to-amber-600 xl:w-auto"
                                                >
                                                    <Play class="size-3" />
                                                    Đồng bộ
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="localIndexes.length === 0">
                                        <td
                                            colspan="7"
                                            class="p-8 text-center font-semibold text-muted-foreground italic"
                                        >
                                            Không cấu hình bất kỳ chỉ mục tìm
                                            kiếm nào trên Scout.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Search Performance & Metrics section -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Latency chart -->
                    <Card
                        class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                    >
                        <CardHeader
                            class="border-b border-border/40 bg-muted/10 p-4 pb-3"
                        >
                            <CardTitle
                                class="flex items-center gap-1.5 text-[10px] font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                            >
                                <TrendingUp class="size-4 text-cyan-500" />
                                Độ trễ trung bình của chỉ mục (ms)
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4 pt-5">
                            <div
                                v-for="lat in props.statistics.latency_by_index"
                                :key="lat.index_name"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex justify-between text-xs font-semibold"
                                >
                                    <span
                                        class="font-mono text-slate-700 dark:text-slate-300"
                                        >{{ lat.index_name }} ({{
                                            lat.total_count
                                        }}
                                        lượt)</span
                                    >
                                    <span
                                        class="font-mono font-bold text-cyan-500"
                                        >{{ lat.avg_latency }}ms</span
                                    >
                                </div>
                                <div
                                    class="flex h-3 w-full overflow-hidden rounded-full border border-border/40 bg-muted shadow-inner"
                                >
                                    <!-- Draw bar indicator based on latency (max visual 300ms) -->
                                    <div
                                        :class="[
                                            'h-full rounded-full transition-all duration-700',
                                            lat.avg_latency < 30
                                                ? 'bg-cyan-500'
                                                : lat.avg_latency < 100
                                                  ? 'bg-amber-500'
                                                  : 'bg-rose-500',
                                        ]"
                                        :style="{
                                            width: `${Math.min((lat.avg_latency / 300) * 100, 100)}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>
                            <div
                                v-if="
                                    props.statistics.latency_by_index.length ===
                                    0
                                "
                                class="py-6 text-center text-xs text-muted-foreground italic"
                            >
                                Chưa ghi nhận dữ liệu đo lường hiệu năng tìm
                                kiếm nào...
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Searches log -->
                    <Card
                        class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                    >
                        <CardHeader
                            class="border-b border-border/40 bg-muted/10 p-4 pb-3"
                        >
                            <CardTitle
                                class="flex items-center gap-1.5 text-[10px] font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                            >
                                <Clock class="size-4 text-muted-foreground" />
                                Nhật ký lượt truy vấn mới nhất
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div
                                class="max-h-[220px] overflow-x-auto overflow-y-auto"
                            >
                                <table
                                    class="w-full border-collapse text-left text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="border-b border-border/45 bg-muted/20 text-[10px] font-bold text-muted-foreground uppercase"
                                        >
                                            <th class="p-3 pl-5">Từ khóa</th>
                                            <th class="p-3">Phân mục</th>
                                            <th class="p-3 text-center">
                                                Độ trễ
                                            </th>
                                            <th class="p-3 pr-5 text-right">
                                                Thời gian
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="dark:text-slate-350 divide-y divide-border/20 font-mono text-[11px] text-slate-700"
                                    >
                                        <tr
                                            v-for="search in props.statistics
                                                .latest_searches"
                                            :key="search.created_at"
                                            class="transition-colors hover:bg-muted/15"
                                        >
                                            <td
                                                class="p-3 pl-5 font-semibold text-slate-800 dark:text-slate-100"
                                            >
                                                "{{ search.keyword }}"
                                            </td>
                                            <td
                                                class="p-3 text-muted-foreground"
                                            >
                                                {{ search.index_name }}
                                            </td>
                                            <td
                                                class="p-3 text-center font-bold"
                                            >
                                                <span
                                                    :class="
                                                        search.latency_ms < 30
                                                            ? 'text-cyan-500'
                                                            : 'text-amber-500'
                                                    "
                                                >
                                                    {{ search.latency_ms }}ms
                                                </span>
                                            </td>
                                            <td
                                                class="p-3 pr-5 text-right text-[10px] text-muted-foreground"
                                            >
                                                {{ search.created_at }}
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                props.statistics.latest_searches
                                                    .length === 0
                                            "
                                        >
                                            <td
                                                colspan="4"
                                                class="p-6 text-center font-sans text-xs text-muted-foreground italic"
                                            >
                                                Không có nhật ký tìm kiếm gần
                                                đây.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Right Side: AI Diagnostics & Top Keywords (4 columns) -->
            <div class="flex flex-col gap-6 lg:col-span-4">
                <!-- AI Diagnostics Advisor Panel -->
                <Card
                    class="flex flex-col overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader
                        class="border-b border-border/40 bg-muted/10 p-4 pb-3"
                    >
                        <CardTitle
                            class="flex items-center gap-1.5 text-[10px] font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                        >
                            <Sparkles
                                class="size-4 animate-pulse text-amber-500"
                            />
                            Trợ lý chẩn đoán AI
                        </CardTitle>
                    </CardHeader>
                    <CardContent
                        class="flex flex-1 flex-col justify-between gap-4 p-5"
                    >
                        <!-- Grade & Health Score Display -->
                        <div
                            class="flex items-center gap-4 rounded-xl border border-border/40 bg-muted/20 p-4 backdrop-blur-sm"
                        >
                            <div
                                :class="[
                                    'flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-xl border text-xl font-black shadow-xs',
                                    searchHealthGrade.color,
                                ]"
                            >
                                {{ searchHealthGrade.grade }}
                            </div>
                            <div class="flex min-w-0 flex-col">
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >{{ searchHealthGrade.label }}</span
                                >
                                <span
                                    class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >{{ searchHealthGrade.desc }}</span
                                >
                            </div>
                        </div>

                        <!-- Advices Checklist -->
                        <div class="flex-1 space-y-3">
                            <span
                                class="block text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                                >Báo cáo chẩn đoán hệ thống</span
                            >

                            <!-- Connection Status -->
                            <div
                                class="flex items-start gap-2.5 rounded-lg border border-border/20 bg-muted/5 p-2.5"
                            >
                                <div class="mt-0.5 shrink-0">
                                    <CheckCircle2
                                        v-if="connectionAdvice.status === 'ok'"
                                        class="size-4 text-emerald-500"
                                    />
                                    <AlertCircle
                                        v-else
                                        class="size-4 text-rose-500"
                                    />
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span
                                        class="text-[10px] font-bold text-slate-700 dark:text-slate-300"
                                        >Kết nối Meilisearch</span
                                    >
                                    <p
                                        class="mt-0.5 text-[9px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        {{ connectionAdvice.text }}
                                    </p>
                                </div>
                            </div>

                            <!-- Driver Config -->
                            <div
                                class="flex items-start gap-2.5 rounded-lg border border-border/20 bg-muted/5 p-2.5"
                            >
                                <div class="mt-0.5 shrink-0">
                                    <CheckCircle2
                                        v-if="driverAdvice.status === 'ok'"
                                        class="size-4 text-emerald-500"
                                    />
                                    <AlertTriangle
                                        v-else
                                        class="size-4 text-amber-500"
                                    />
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span
                                        class="text-[10px] font-bold text-slate-700 dark:text-slate-300"
                                        >Cấu hình Scout Driver</span
                                    >
                                    <p
                                        class="mt-0.5 text-[9px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        {{ driverAdvice.text }}
                                    </p>
                                </div>
                            </div>

                            <!-- Sync State -->
                            <div
                                class="flex items-start gap-2.5 rounded-lg border border-border/20 bg-muted/5 p-2.5"
                            >
                                <div class="mt-0.5 shrink-0">
                                    <CheckCircle2
                                        v-if="indexSyncAdvice.status === 'ok'"
                                        class="size-4 text-emerald-500"
                                    />
                                    <AlertTriangle
                                        v-else
                                        class="size-4 text-amber-500"
                                    />
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span
                                        class="text-[10px] font-bold text-slate-700 dark:text-slate-300"
                                        >Đồng bộ chỉ mục</span
                                    >
                                    <p
                                        class="mt-0.5 text-[9px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        {{ indexSyncAdvice.text }}
                                    </p>
                                </div>
                            </div>

                            <!-- Performance latency -->
                            <div
                                class="flex items-start gap-2.5 rounded-lg border border-border/20 bg-muted/5 p-2.5"
                            >
                                <div class="mt-0.5 shrink-0">
                                    <CheckCircle2
                                        v-if="latencyAdvice.status === 'ok'"
                                        class="size-4 text-emerald-500"
                                    />
                                    <AlertTriangle
                                        v-else
                                        class="size-4 text-amber-500"
                                    />
                                </div>
                                <div class="flex min-w-0 flex-col">
                                    <span
                                        class="text-[10px] font-bold text-slate-700 dark:text-slate-300"
                                        >Độ trễ tìm kiếm</span
                                    >
                                    <p
                                        class="mt-0.5 text-[9px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        {{ latencyAdvice.text }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="border-t border-border/20 pt-2 text-[9px] leading-normal font-semibold text-muted-foreground"
                        >
                            📊 Trợ lý chẩn đoán AI cập nhật trạng thái tự động
                            theo thời gian thực.
                        </div>
                    </CardContent>
                </Card>

                <!-- Top Search Keywords Card -->
                <Card
                    class="flex flex-col justify-between overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <div>
                        <CardHeader
                            class="flex flex-row flex-wrap items-center justify-between gap-2 border-b border-border/40 bg-muted/10 p-4 pb-3"
                        >
                            <div>
                                <CardTitle
                                    class="flex items-center gap-1.5 text-[10px] font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                                >
                                    <TrendingUp
                                        class="size-4 text-violet-500"
                                    />
                                    Từ khóa được tìm kiếm nhiều nhất
                                </CardTitle>
                            </div>

                            <Button
                                v-if="props.statistics.total_searches > 0"
                                @click="clearSearchStatistics"
                                :disabled="isClearingStats"
                                variant="outline"
                                size="sm"
                                class="h-7 cursor-pointer rounded-lg border-border text-[10px] text-muted-foreground hover:text-rose-500"
                            >
                                <Trash2 class="mr-1 size-3" />
                                Xóa số liệu
                            </Button>
                        </CardHeader>

                        <CardContent class="p-0">
                            <div class="overflow-x-auto">
                                <table
                                    class="w-full border-collapse text-left text-xs"
                                >
                                    <thead>
                                        <tr
                                            class="border-b border-border/45 bg-muted/20 text-[10px] font-bold text-muted-foreground uppercase"
                                        >
                                            <th
                                                class="w-12 p-3 pl-5 text-center"
                                            >
                                                Hạng
                                            </th>
                                            <th class="p-3">Từ khóa</th>
                                            <th class="p-3">Chỉ mục</th>
                                            <th class="p-3 text-center">
                                                Số lượt
                                            </th>
                                            <th class="p-3 pr-5 text-right">
                                                Độ trễ TB
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="dark:text-slate-350 divide-y divide-border/20 font-mono text-slate-700"
                                    >
                                        <tr
                                            v-for="kw in props.statistics
                                                .top_keywords"
                                            :key="kw.rank"
                                            class="transition-colors hover:bg-muted/15"
                                        >
                                            <td
                                                class="p-3 pl-5 text-center font-bold"
                                            >
                                                <span
                                                    v-if="kw.rank === 1"
                                                    class="text-amber-500"
                                                    >🥇 1</span
                                                >
                                                <span
                                                    v-else-if="kw.rank === 2"
                                                    class="text-zinc-400"
                                                    >🥈 2</span
                                                >
                                                <span
                                                    v-else-if="kw.rank === 3"
                                                    class="text-amber-700"
                                                    >🥉 3</span
                                                >
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >{{ kw.rank }}</span
                                                >
                                            </td>
                                            <td
                                                class="p-3 font-bold text-slate-800 dark:text-slate-100"
                                            >
                                                "{{ kw.keyword }}"
                                            </td>
                                            <td
                                                class="p-3 text-[10px] font-semibold text-muted-foreground"
                                            >
                                                {{ kw.index_name }}
                                            </td>
                                            <td
                                                class="p-3 text-center font-bold text-violet-500"
                                            >
                                                {{ kw.search_count }}
                                            </td>
                                            <td
                                                class="p-3 pr-5 text-right font-bold text-cyan-500"
                                            >
                                                {{ kw.avg_latency }}ms
                                            </td>
                                        </tr>
                                        <tr
                                            v-if="
                                                props.statistics.top_keywords
                                                    .length === 0
                                            "
                                        >
                                            <td
                                                colspan="5"
                                                class="p-10 text-center font-sans font-semibold text-muted-foreground italic"
                                            >
                                                Chưa ghi nhận từ khóa tìm kiếm
                                                phổ biến nào...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </div>

                    <div
                        class="rounded-b-2xl border-t border-border/20 bg-muted/10 p-4 font-sans text-[10px] leading-normal font-semibold text-muted-foreground"
                    >
                        💡
                        <span
                            class="text-slate-750 font-bold dark:text-slate-300"
                            >Gợi ý tối ưu:</span
                        >
                        Đối với từ khóa tìm kiếm phổ biến có thời gian phản hồi
                        cao (> 50ms), bạn có thể tối ưu hóa bằng cách giới hạn
                        thuộc tính tìm kiếm `searchableAttributes` tinh gọn trên
                        cài đặt chỉ mục của Meilisearch.
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>
