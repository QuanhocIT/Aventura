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
    CheckCircle,
    CheckCircle2,
    XCircle,
    Sliders,
    Sparkles,
    TrendingUp,
    ExternalLink,
    Grid,
    HelpCircle,
    AlertTriangle
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { PageHeader, TerminalCard, StatusBadge, LedIndicator } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content || '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ index_name: indexName, action })
    }).then(async (res) => {
        if (!res.ok) {
            throw new Error('Yêu cầu thất bại');
        }

        const data = await res.json();

        if (data.success) {
            // Update local state sync status to pending
            const targetIndex = localIndexes.value.find(idx => idx.index_name === indexName);

            if (targetIndex) {
                targetIndex.sync_status = data.sync_status;
            }

            return data.message || 'Lệnh đồng bộ chỉ mục đã được đưa vào hàng đợi';
        }

        throw new Error(data.message || 'Lỗi không xác định');
    });

    toast.promise(promise, {
        loading: `Đang gửi lệnh ${action === 'import' ? 'Đồng bộ' : 'Xóa sạch'} đến hàng đợi...`,
        success: (msg: any) => msg,
        error: (err: any) => `Lỗi: ${err.message || err}`
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
    if (!(await confirmDialog({ title: 'Xác nhận thao tác', description: 'Bạn có chắc chắn muốn xóa toàn bộ lịch sử và số liệu thống kê tìm kiếm?' }))) {
        return;
    }
    
    isClearingStats.value = true;
    router.post('/super-admin/meilisearch-console/clear-stats', {}, {
        onSuccess: () => {
            toast.success('Đã xóa dữ liệu thống kê thành công.');
            isClearingStats.value = false;
        },
        onError: () => {
            toast.error('Không thể xóa dữ liệu thống kê.');
            isClearingStats.value = false;
        }
    });
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
        case 'pending': return 'Đang chờ';
        case 'processing': return 'Đang xử lý';
        case 'success': return 'Thành công';
        case 'failed': return 'Thất bại';
        default: return 'Sẵn sàng';
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
    props.indexes.forEach(idx => {
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
        return { grade: 'OFFLINE', color: 'text-rose-500 bg-rose-500/10 border-rose-500/20', label: 'Mất kết nối', desc: 'Máy chủ Meilisearch đang ngoại tuyến hoặc cấu hình sai Host/Port.' };
    }
    
    const outOfSyncCount = props.indexes.filter(idx => idx.out_of_sync).length;
    
    if (props.connection.driver !== 'meilisearch') {
        return { grade: 'C', color: 'text-amber-600 bg-amber-500/10 border-amber-500/20', label: 'Cấu hình sai Driver', desc: 'Máy chủ Meilisearch online nhưng Laravel Scout driver không được thiết lập là meilisearch.' };
    }
    
    if (outOfSyncCount > 0) {
        if (overallSyncPercentage.value < 70) {
            return { grade: 'C', color: 'text-rose-500 bg-rose-500/10 border-rose-500/20', label: 'Mất đồng bộ nghiêm trọng', desc: `Phát hiện ${outOfSyncCount} chỉ mục có chênh lệch bản ghi lớn (Tỷ lệ khớp < 70%).` };
        }

        return { grade: 'B', color: 'text-amber-500 bg-amber-500/10 border-amber-500/20', label: 'Chưa đồng bộ đầy đủ', desc: `Phát hiện ${outOfSyncCount} chỉ mục chưa được đồng bộ hoàn toàn với Database.` };
    }

    if (props.statistics.average_latency > 50) {
        return { grade: 'A', color: 'text-cyan-500 bg-cyan-500/10 border-cyan-500/20', label: 'Hoạt động - Độ trễ cao', desc: 'Chỉ mục đồng bộ đầy đủ nhưng độ trễ tìm kiếm trung bình còn cao (>50ms).' };
    }

    return { grade: 'A+', color: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20', label: 'Tối ưu hoàn hảo', desc: 'Hệ thống tìm kiếm online, driver Scout chính xác và 100% chỉ mục đã khớp.' };
});

const connectionAdvice = computed(() => {
    if (!props.connection.online) {
        return { status: 'error', text: 'Meilisearch offline. Cần chạy dịch vụ meilisearch.exe hoặc kiểm tra cổng 7700 trên server.' };
    }

    return { status: 'ok', text: `Kết nối máy chủ Meilisearch trên ${props.connection.host} ổn định.` };
});

const driverAdvice = computed(() => {
    if (props.connection.driver !== 'meilisearch') {
        return { status: 'warning', text: `Scout driver đang là '${props.connection.driver}'. Cần đổi SCOUT_DRIVER=meilisearch trong tệp .env.` };
    }

    return { status: 'ok', text: 'Scout driver cấu hình chính xác.' };
});

const indexSyncAdvice = computed(() => {
    const outOfSyncCount = props.indexes.filter(idx => idx.out_of_sync).length;

    if (outOfSyncCount > 0) {
        return { status: 'warning', text: `Có ${outOfSyncCount} chỉ mục bị lệch. Hãy bấm nút "Đồng bộ (Import)" tương ứng để đồng bộ.` };
    }

    return { status: 'ok', text: 'Tất cả chỉ mục tìm kiếm khớp hoàn toàn với CSDL chính.' };
});

const latencyAdvice = computed(() => {
    if (props.statistics.average_latency > 50) {
        return { status: 'warning', text: 'Độ trễ trung bình cao (> 50ms). Khuyên lọc bớt searchableAttributes.' };
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
                    class="gap-1.5 rounded-xl border-border hover:bg-muted font-bold text-xs h-9 cursor-pointer"
                >
                    <RotateCw class="size-3.5" />
                    Làm mới dữ liệu
                </Button>
            </template>
        </PageHeader>

        <!-- Connection State & Summary Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Connection Status -->
            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardContent class="flex items-center gap-4 p-4">
                    <div :class="['rounded-xl p-2.5 shrink-0', props.connection.online ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500']">
                        <Activity class="size-5" />
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Kết nối máy chủ</span>
                        <span class="text-lg font-bold tracking-tight flex items-center gap-1.5 mt-0.5">
                            {{ props.connection.online ? 'ONLINE' : 'OFFLINE' }}
                            <span :class="['relative flex h-2 w-2', props.connection.online ? 'text-emerald-500' : 'text-rose-500']">
                                <span :class="['animate-ping absolute inline-flex h-full w-full rounded-full opacity-75', props.connection.online ? 'bg-emerald-400' : 'bg-rose-400']"></span>
                                <span :class="['relative inline-flex rounded-full h-2 w-2', props.connection.online ? 'bg-emerald-500' : 'bg-rose-500']"></span>
                            </span>
                        </span>
                        <span class="text-[9px] text-muted-foreground font-mono truncate mt-0.5 font-semibold">
                            {{ props.connection.host }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Database Size -->
            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-orange-500/10 p-2.5 text-orange-500 shrink-0">
                        <Database class="size-5" />
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Kích thước DB</span>
                        <span class="text-lg font-bold tracking-tight mt-0.5">
                            {{ formatBytes(props.connection.database_size) }}
                        </span>
                        <span class="text-[9px] text-muted-foreground font-semibold mt-0.5">
                            Dung lượng cache LMDB
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Search Volume -->
            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-violet-500/10 p-2.5 text-violet-500 shrink-0">
                        <Search class="size-5" />
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Tổng lượt truy vấn</span>
                        <span class="text-lg font-bold tracking-tight text-violet-500 mt-0.5">
                            {{ props.statistics.total_searches }}
                        </span>
                        <span class="text-[9px] text-muted-foreground font-semibold mt-0.5">
                            Lượt tìm kiếm hệ thống
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Response Latency -->
            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-cyan-500/10 p-2.5 text-cyan-500 shrink-0">
                        <Clock class="size-5" />
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Độ trễ trung bình</span>
                        <span class="text-lg font-bold tracking-tight text-cyan-500 mt-0.5">
                            {{ props.statistics.average_latency }}ms
                        </span>
                        <span class="text-[9px] text-muted-foreground font-semibold mt-0.5">
                            Thời gian phản hồi bình quân
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Global Index Sync Progress Bar Card -->
        <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl overflow-hidden p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-3">
                <div class="flex items-center gap-2">
                    <div class="p-2 rounded-xl bg-amber-500/10 text-amber-500">
                        <TrendingUp class="size-4" />
                    </div>
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                            Tiến độ đồng bộ chỉ mục toàn cục
                        </h3>
                        <p class="text-[10px] text-muted-foreground font-semibold mt-0.5">
                            Tỉ lệ khớp tài liệu trong chỉ mục so với tổng số bản ghi trong Cơ sở dữ liệu.
                        </p>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5 shrink-0 bg-amber-500/10 border border-amber-500/20 px-3 py-1.5 rounded-xl">
                    <span class="text-lg font-black text-amber-500 font-mono leading-none">{{ overallSyncPercentage }}%</span>
                    <span class="text-[10px] font-semibold text-muted-foreground uppercase leading-none">Đã khớp</span>
                </div>
            </div>
            
            <div class="w-full bg-muted border border-border/40 rounded-full h-3 overflow-hidden flex shadow-inner">
                <div 
                    class="h-full rounded-full bg-gradient-to-r from-orange-500 via-amber-500 to-yellow-500 transition-all duration-700 relative shadow-sm"
                    :style="{ width: `${overallSyncPercentage}%` }"
                >
                    <!-- Glossy overlay for premium look -->
                    <div class="absolute inset-0 bg-linear-to-b from-white/10 to-transparent"></div>
                </div>
            </div>
            
            <div class="flex justify-between items-center mt-2.5 text-[9px] text-muted-foreground font-mono font-semibold">
                <span>0%</span>
                <span class="font-sans text-slate-700 dark:text-slate-300">
                    {{ overallSyncPercentage === 100 ? '✅ Toàn bộ chỉ mục đã khớp dữ liệu.' : '⚠️ Có chỉ mục chưa đồng bộ, cần chạy tác vụ Import.' }}
                </span>
                <span>100%</span>
            </div>
        </Card>

        <!-- Connection Error Alert if Offline -->
        <div 
            v-if="!props.connection.online && props.connection.error" 
            class="rounded-2xl border border-rose-500/20 bg-rose-500/[0.04] p-4 text-rose-800 dark:text-rose-350 backdrop-blur-md flex gap-3 text-xs"
        >
            <AlertCircle class="size-5 text-rose-500 shrink-0 mt-0.5" />
            <div class="space-y-1">
                <span class="font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">Lỗi kết nối tới Meilisearch</span>
                <p class="text-xs text-slate-700 dark:text-slate-350/80 font-mono break-all leading-normal">{{ props.connection.error }}</p>
                <p class="text-[10px] text-muted-foreground font-semibold mt-1.5 leading-normal">
                    Vui lòng kiểm tra lại dịch vụ Meilisearch trên cổng `7700` hoặc cập nhật biến môi trường `MEILISEARCH_HOST` trong file `.env`.
                </p>
            </div>
        </div>

        <!-- Workspace Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Side: Index Sync Tool & Performance Metrics (8 columns) -->
            <div class="lg:col-span-8 flex flex-col gap-6">
                <!-- Index Sync Tool Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardHeader class="border-b border-border/40 bg-muted/10 p-5 flex flex-row flex-wrap items-center justify-between gap-4">
                        <div>
                            <CardTitle class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                                <Sliders class="size-5 text-orange-500" />
                                Danh sách và Đồng bộ chỉ mục (Index Sync Tool)
                            </CardTitle>
                            <CardDescription class="text-xs text-muted-foreground font-semibold mt-1">
                                Kiểm tra chênh lệch số lượng bản ghi giữa cơ sở dữ liệu gốc và chỉ mục tìm kiếm Meilisearch, thực hiện đồng bộ lại chỉ mục.
                            </CardDescription>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase text-muted-foreground tracking-wider">Driver Scout:</span>
                            <span class="bg-orange-500/10 border border-orange-500/15 text-orange-600 dark:text-orange-400 font-mono px-2.5 py-0.5 text-[10px] rounded-lg font-black uppercase">
                                {{ props.connection.driver }}
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr class="border-b border-border/45 bg-muted/20 text-muted-foreground font-bold text-[10px] uppercase tracking-wider">
                                        <th class="py-3.5 px-5">Chỉ mục (Index)</th>
                                        <th class="py-3.5 px-4">Model tương ứng</th>
                                        <th class="py-3.5 px-4 text-center">Bản ghi trong DB</th>
                                        <th class="py-3.5 px-4 text-center">Bản ghi trong Index</th>
                                        <th class="py-3.5 px-4 text-center">Tỷ lệ đồng bộ</th>
                                        <th class="py-3.5 px-4 text-center">Trạng thái cuối</th>
                                        <th class="py-3.5 px-5 text-right">Thao tác đồng bộ</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/25">
                                    <tr 
                                        v-for="idx in localIndexes" 
                                        :key="idx.index_name" 
                                        class="hover:bg-muted/15 transition-colors group"
                                    >
                                        <td class="py-3.5 px-5">
                                            <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                                {{ idx.label }}
                                                <span class="bg-background dark:bg-slate-900 border border-border/40 text-muted-foreground font-mono text-[9px] rounded px-1.5 py-0.5 font-bold">
                                                    {{ idx.index_name }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 font-mono text-[10px] text-muted-foreground font-semibold">
                                            {{ idx.model }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-bold font-mono text-slate-700 dark:text-slate-350">
                                            {{ idx.db_records_count }}
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-bold font-mono">
                                            <span :class="idx.out_of_sync ? 'text-orange-500' : 'text-slate-700 dark:text-slate-350'">
                                                {{ idx.documents_count }}
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <div class="flex flex-col gap-1 items-center max-w-[120px] mx-auto">
                                                <div class="w-full bg-muted border border-border/40 rounded-full h-1.5 overflow-hidden flex">
                                                    <div 
                                                        :class="['h-full rounded-full transition-all duration-500', idx.out_of_sync ? 'bg-gradient-to-r from-orange-500 to-amber-500' : 'bg-gradient-to-r from-amber-500 to-yellow-500']" 
                                                        :style="{ width: `${getIndexVisualPercentage(idx)}%` }"
                                                    ></div>
                                                </div>
                                                <span class="text-[9px] text-muted-foreground font-mono font-bold">{{ getIndexVisualPercentage(idx) }}% khớp</span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="flex flex-col items-center gap-0.5">
                                                <span :class="['inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg text-[9px] font-bold border', getSyncBadgeClass(idx.sync_status.status)]">
                                                    {{ getSyncStatusLabel(idx.sync_status.status) }}
                                                </span>
                                                <span v-if="idx.sync_status.completed_at" class="text-[9px] text-muted-foreground font-semibold mt-0.5">
                                                    {{ idx.sync_status.action === 'import' ? 'Đồng bộ' : 'Xóa' }} 
                                                    {{ idx.sync_status.completed_at.split(' ')[1] }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-3.5 px-5 text-right pr-6">
                                            <div class="flex justify-end gap-2">
                                                <!-- Flush Button -->
                                                <Button
                                                    @click="triggerSync(idx.index_name, 'flush')"
                                                    :disabled="isSyncing[`${idx.index_name}_flush`] || isSyncing[`${idx.index_name}_import`] || idx.is_indexing || !props.connection.online"
                                                    variant="ghost"
                                                    size="sm"
                                                    class="h-8 text-xs text-rose-500 hover:text-rose-600 hover:bg-rose-500/10 border border-border rounded-xl font-bold cursor-pointer"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                    Xóa (Flush)
                                                </Button>

                                                <!-- Import Button -->
                                                <Button
                                                    @click="triggerSync(idx.index_name, 'import')"
                                                    :disabled="isSyncing[`${idx.index_name}_import`] || isSyncing[`${idx.index_name}_flush`] || idx.is_indexing || !props.connection.online"
                                                    size="sm"
                                                    class="h-8 text-xs rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white font-bold cursor-pointer shadow-xs gap-1 border-none"
                                                >
                                                    <Play class="size-3" />
                                                    Đồng bộ (Import)
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="localIndexes.length === 0">
                                        <td colspan="7" class="p-8 text-center text-muted-foreground font-semibold italic">
                                            Không cấu hình bất kỳ chỉ mục tìm kiếm nào trên Scout.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Search Performance & Metrics section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Latency chart -->
                    <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex flex-col justify-between">
                        <CardHeader class="pb-3 border-b border-border/40 bg-muted/10 p-4">
                            <CardTitle class="text-[10px] font-black uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <TrendingUp class="size-4 text-cyan-500" />
                                Độ trễ trung bình của chỉ mục (Latency ms)
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="pt-5 space-y-4">
                            <div v-for="lat in props.statistics.latency_by_index" :key="lat.index_name" class="space-y-1.5">
                                <div class="flex justify-between text-xs font-semibold">
                                    <span class="text-slate-700 dark:text-slate-300 font-mono">{{ lat.index_name }} ({{ lat.total_count }} lượt)</span>
                                    <span class="text-cyan-500 font-mono font-bold">{{ lat.avg_latency }}ms</span>
                                </div>
                                <div class="w-full bg-muted border border-border/40 rounded-full h-3 overflow-hidden flex shadow-inner">
                                    <!-- Draw bar indicator based on latency (max visual 300ms) -->
                                    <div 
                                        :class="['h-full rounded-full transition-all duration-700', lat.avg_latency < 30 ? 'bg-cyan-500' : (lat.avg_latency < 100 ? 'bg-amber-500' : 'bg-rose-500')]" 
                                        :style="{ width: `${Math.min((lat.avg_latency / 300) * 100, 100)}%` }"
                                    ></div>
                                </div>
                            </div>
                            <div v-if="props.statistics.latency_by_index.length === 0" class="text-muted-foreground text-xs italic text-center py-6">
                                Chưa ghi nhận dữ liệu đo lường hiệu năng tìm kiếm nào...
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recent Searches log -->
                    <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex flex-col justify-between">
                        <CardHeader class="pb-3 border-b border-border/40 bg-muted/10 p-4">
                            <CardTitle class="text-[10px] font-black uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                <Clock class="size-4 text-muted-foreground" />
                                Nhật ký lượt truy vấn mới nhất
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div class="overflow-x-auto max-h-[220px] overflow-y-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-border/45 bg-muted/20 text-[10px] uppercase font-bold text-muted-foreground">
                                            <th class="p-3 pl-5">Từ khóa</th>
                                            <th class="p-3">Phân mục</th>
                                            <th class="p-3 text-center">Độ trễ</th>
                                            <th class="p-3 text-right pr-5">Thời gian</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/20 text-slate-700 dark:text-slate-350 font-mono text-[11px]">
                                        <tr v-for="search in props.statistics.latest_searches" :key="search.created_at" class="hover:bg-muted/15 transition-colors">
                                            <td class="p-3 pl-5 font-semibold text-slate-800 dark:text-slate-100">"{{ search.keyword }}"</td>
                                            <td class="p-3 text-muted-foreground">{{ search.index_name }}</td>
                                            <td class="p-3 text-center font-bold">
                                                <span :class="search.latency_ms < 30 ? 'text-cyan-500' : 'text-amber-500'">
                                                    {{ search.latency_ms }}ms
                                                </span>
                                            </td>
                                            <td class="p-3 text-right pr-5 text-muted-foreground text-[10px]">{{ search.created_at }}</td>
                                        </tr>
                                        <tr v-if="props.statistics.latest_searches.length === 0">
                                            <td colspan="4" class="p-6 text-center text-muted-foreground italic font-sans text-xs">
                                                Không có nhật ký tìm kiếm gần đây.
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
            <div class="lg:col-span-4 flex flex-col gap-6">
                <!-- AI Diagnostics Advisor Panel -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex flex-col">
                    <CardHeader class="pb-3 border-b border-border/40 bg-muted/10 p-4">
                        <CardTitle class="text-[10px] font-black uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                            <Sparkles class="size-4 text-amber-500 animate-pulse" />
                            AI Diagnostics Advisor
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5 flex-1 flex flex-col justify-between gap-4">
                        <!-- Grade & Health Score Display -->
                        <div class="flex items-center gap-4 bg-muted/20 border border-border/40 p-4 rounded-xl backdrop-blur-sm">
                            <div :class="['w-16 h-16 rounded-xl flex flex-col items-center justify-center border font-black text-xl shadow-xs shrink-0', searchHealthGrade.color]">
                                {{ searchHealthGrade.grade }}
                            </div>
                            <div class="flex flex-col min-w-0">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ searchHealthGrade.label }}</span>
                                <span class="text-[10px] text-muted-foreground mt-0.5 leading-normal font-semibold">{{ searchHealthGrade.desc }}</span>
                            </div>
                        </div>

                        <!-- Advices Checklist -->
                        <div class="space-y-3 flex-1">
                            <span class="text-[10px] font-black text-muted-foreground uppercase tracking-wider block">Báo cáo chẩn đoán hệ thống</span>
                            
                            <!-- Connection Status -->
                            <div class="flex items-start gap-2.5 bg-muted/5 border border-border/20 p-2.5 rounded-lg">
                                <div class="mt-0.5 shrink-0">
                                    <CheckCircle2 v-if="connectionAdvice.status === 'ok'" class="size-4 text-emerald-500" />
                                    <AlertCircle v-else class="size-4 text-rose-500" />
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300">Kết nối Meilisearch</span>
                                    <p class="text-[9px] text-muted-foreground mt-0.5 leading-normal font-semibold">{{ connectionAdvice.text }}</p>
                                </div>
                            </div>

                            <!-- Driver Config -->
                            <div class="flex items-start gap-2.5 bg-muted/5 border border-border/20 p-2.5 rounded-lg">
                                <div class="mt-0.5 shrink-0">
                                    <CheckCircle2 v-if="driverAdvice.status === 'ok'" class="size-4 text-emerald-500" />
                                    <AlertTriangle v-else class="size-4 text-amber-500" />
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300">Cấu hình Scout Driver</span>
                                    <p class="text-[9px] text-muted-foreground mt-0.5 leading-normal font-semibold">{{ driverAdvice.text }}</p>
                                </div>
                            </div>

                            <!-- Sync State -->
                            <div class="flex items-start gap-2.5 bg-muted/5 border border-border/20 p-2.5 rounded-lg">
                                <div class="mt-0.5 shrink-0">
                                    <CheckCircle2 v-if="indexSyncAdvice.status === 'ok'" class="size-4 text-emerald-500" />
                                    <AlertTriangle v-else class="size-4 text-amber-500" />
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300">Đồng bộ chỉ mục</span>
                                    <p class="text-[9px] text-muted-foreground mt-0.5 leading-normal font-semibold">{{ indexSyncAdvice.text }}</p>
                                </div>
                            </div>

                            <!-- Performance latency -->
                            <div class="flex items-start gap-2.5 bg-muted/5 border border-border/20 p-2.5 rounded-lg">
                                <div class="mt-0.5 shrink-0">
                                    <CheckCircle2 v-if="latencyAdvice.status === 'ok'" class="size-4 text-emerald-500" />
                                    <AlertTriangle v-else class="size-4 text-amber-500" />
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300">Độ trễ tìm kiếm</span>
                                    <p class="text-[9px] text-muted-foreground mt-0.5 leading-normal font-semibold">{{ latencyAdvice.text }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 text-[9px] text-muted-foreground font-semibold leading-normal border-t border-border/20">
                            📊 AI Diagnostics Advisor cập nhật trạng thái tự động theo thời gian thực.
                        </div>
                    </CardContent>
                </Card>

                <!-- Top Search Keywords Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex flex-col justify-between">
                    <div>
                        <CardHeader class="pb-3 border-b border-border/40 bg-muted/10 p-4 flex flex-row items-center justify-between flex-wrap gap-2">
                            <div>
                                <CardTitle class="text-[10px] font-black uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                    <TrendingUp class="size-4 text-violet-500" />
                                    Từ khóa được tìm kiếm nhiều nhất
                                </CardTitle>
                            </div>
                            
                            <Button
                                v-if="props.statistics.total_searches > 0"
                                @click="clearSearchStatistics"
                                :disabled="isClearingStats"
                                variant="outline"
                                size="sm"
                                class="h-7 text-[10px] text-muted-foreground hover:text-rose-500 border-border rounded-lg cursor-pointer"
                            >
                                <Trash2 class="size-3 mr-1" />
                                Xóa số liệu
                            </Button>
                        </CardHeader>
                        
                        <CardContent class="p-0">
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs border-collapse">
                                    <thead>
                                        <tr class="border-b border-border/45 bg-muted/20 text-[10px] uppercase font-bold text-muted-foreground">
                                            <th class="p-3 pl-5 text-center w-12">Hạng</th>
                                            <th class="p-3">Từ khóa (Keyword)</th>
                                            <th class="p-3">Chỉ mục</th>
                                            <th class="p-3 text-center">Số lượt</th>
                                            <th class="p-3 text-right pr-5">Độ trễ TB</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border/20 text-slate-700 dark:text-slate-350 font-mono">
                                        <tr v-for="kw in props.statistics.top_keywords" :key="kw.rank" class="hover:bg-muted/15 transition-colors">
                                            <td class="p-3 pl-5 text-center font-bold">
                                                <span v-if="kw.rank === 1" class="text-amber-500">🥇 1</span>
                                                <span v-else-if="kw.rank === 2" class="text-zinc-400">🥈 2</span>
                                                <span v-else-if="kw.rank === 3" class="text-amber-700">🥉 3</span>
                                                <span v-else class="text-muted-foreground">{{ kw.rank }}</span>
                                            </td>
                                            <td class="p-3 font-bold text-slate-800 dark:text-slate-100">"{{ kw.keyword }}"</td>
                                            <td class="p-3 text-[10px] text-muted-foreground font-semibold">{{ kw.index_name }}</td>
                                            <td class="p-3 text-center font-bold text-violet-500">{{ kw.search_count }}</td>
                                            <td class="p-3 text-right pr-5 text-cyan-500 font-bold">{{ kw.avg_latency }}ms</td>
                                        </tr>
                                        <tr v-if="props.statistics.top_keywords.length === 0">
                                            <td colspan="5" class="p-10 text-center text-muted-foreground font-semibold italic font-sans">
                                                Chưa ghi nhận từ khóa tìm kiếm phổ biến nào...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </div>
                    
                    <div class="p-4 border-t border-border/20 bg-muted/10 text-[10px] text-muted-foreground leading-normal font-semibold rounded-b-2xl font-sans">
                        💡 <span class="font-bold text-slate-750 dark:text-slate-300">Gợi ý tối ưu:</span> Đối với từ khóa tìm kiếm phổ biến có thời gian phản hồi cao (> 50ms), bạn có thể tối ưu hóa bằng cách giới hạn thuộc tính tìm kiếm `searchableAttributes` tinh gọn trên cài đặt chỉ mục của Meilisearch.
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>
