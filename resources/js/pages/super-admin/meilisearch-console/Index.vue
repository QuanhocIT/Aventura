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
    XCircle,
    Sliders,
    Sparkles,
    TrendingUp,
    ExternalLink,
    Grid,
    HelpCircle
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, TerminalCard, StatusBadge, LedIndicator } from '@/components/super-admin';
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

function clearSearchStatistics() {
    if (!confirm('Bạn có chắc chắn muốn xóa toàn bộ lịch sử và số liệu thống kê tìm kiếm?')) {
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
                    class="gap-2"
                >
                    <RotateCw class="size-4" />
                    Làm mới dữ liệu
                </Button>
            </template>
        </PageHeader>

        <!-- Connection State & Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Connection Status -->
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div :class="['rounded-lg p-2.5', props.connection.online ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400' : 'bg-rose-50 text-rose-600 dark:bg-rose-950/30 dark:text-rose-400']">
                        <Activity class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Kết nối máy chủ</span>
                        <span class="text-xl font-bold tracking-tight flex items-center gap-1.5">
                            {{ props.connection.online ? 'ONLINE' : 'OFFLINE' }}
                            <span :class="['relative flex h-2 w-2', props.connection.online ? 'text-emerald-500' : 'text-rose-500']">
                                <span :class="['animate-ping absolute inline-flex h-full w-full rounded-full opacity-75', props.connection.online ? 'bg-emerald-400' : 'bg-rose-400']"></span>
                                <span :class="['relative inline-flex rounded-full h-2 w-2', props.connection.online ? 'bg-emerald-500' : 'bg-rose-500']"></span>
                            </span>
                        </span>
                        <span class="text-[10px] text-muted-foreground font-mono truncate mt-0.5">
                            {{ props.connection.host }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Database Size -->
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-lg bg-indigo-50 p-2.5 text-indigo-600 dark:bg-indigo-950/30 dark:text-indigo-400">
                        <Database class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Kích thước DB</span>
                        <span class="text-xl font-bold tracking-tight">
                            {{ formatBytes(props.connection.database_size) }}
                        </span>
                        <span class="text-[10px] text-muted-foreground mt-0.5">
                            Dung lượng bộ nhớ đệm LMDB
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Search Volume -->
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-lg bg-violet-50 p-2.5 text-violet-600 dark:bg-violet-950/30 dark:text-violet-400">
                        <Search class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Tổng số lượt truy vấn</span>
                        <span class="text-xl font-bold tracking-tight text-violet-600 dark:text-violet-400">
                            {{ props.statistics.total_searches }}
                        </span>
                        <span class="text-[10px] text-muted-foreground mt-0.5">
                            Lượt tìm kiếm trên hệ thống
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Response Latency -->
            <Card>
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-lg bg-cyan-50 p-2.5 text-cyan-600 dark:bg-cyan-950/30 dark:text-cyan-400">
                        <Clock class="size-5" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-medium text-muted-foreground uppercase tracking-wider">Độ trễ trung bình</span>
                        <span class="text-xl font-bold tracking-tight text-cyan-600 dark:text-cyan-400">
                            {{ props.statistics.average_latency }}ms
                        </span>
                        <span class="text-[10px] text-muted-foreground mt-0.5">
                            Thời gian phản hồi bình quân
                        </span>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Connection Error Alert if Offline -->
        <div 
            v-if="!props.connection.online && props.connection.error" 
            class="bg-rose-50 dark:bg-rose-950/15 border border-red-200 dark:border-rose-900/30 rounded-xl p-4 flex gap-3 text-sm text-rose-800 dark:text-rose-300"
        >
            <AlertCircle class="size-5 text-rose-500 shrink-0 mt-0.5" />
            <div class="space-y-1">
                <span class="font-bold">Lỗi kết nối tới Meilisearch</span>
                <p class="text-xs text-rose-700 dark:text-rose-300/80 font-mono break-all">{{ props.connection.error }}</p>
                <p class="text-xs text-muted-foreground mt-1 leading-relaxed">
                    Vui lòng kiểm tra lại dịch vụ Meilisearch trên cổng `7700` hoặc cập nhật biến môi trường `MEILISEARCH_HOST` trong file `.env`.
                </p>
            </div>
        </div>

        <!-- Section 1: Index Status Dashboard & Sync tool -->
        <Card>
            <CardHeader class="border-b pb-4 flex flex-row flex-wrap items-center justify-between gap-4">
                <div>
                    <CardTitle class="text-lg font-bold flex items-center gap-2">
                        <Sliders class="size-5 text-violet-500" />
                        Danh sách và Đồng bộ chỉ mục (Index Sync Tool)
                    </CardTitle>
                    <CardDescription>
                        Kiểm tra chênh lệch số lượng bản ghi giữa cơ sở dữ liệu gốc và chỉ mục tìm kiếm Meilisearch, thực hiện đồng bộ lại chỉ mục.
                    </CardDescription>
                </div>
                
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted-foreground">Driver Scout hiện tại:</span>
                    <span class="bg-muted border text-indigo-600 dark:text-indigo-400 font-mono px-2 py-0.5 text-xs rounded-md font-bold uppercase">
                        {{ props.connection.driver }}
                    </span>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="border-b bg-muted/50 text-xs font-bold text-muted-foreground">
                                <th class="p-4 pl-6">Chỉ mục (Index)</th>
                                <th class="p-4">Model tương ứng</th>
                                <th class="p-4 text-center">Bản ghi trong DB</th>
                                <th class="p-4 text-center">Bản ghi trong Index</th>
                                <th class="p-4 text-center">Tỷ lệ đồng bộ</th>
                                <th class="p-4 text-center">Trạng thái cuối</th>
                                <th class="p-4 text-right pr-6">Thao tác đồng bộ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr 
                                v-for="idx in localIndexes" 
                                :key="idx.index_name" 
                                class="hover:bg-muted/30 transition-colors"
                            >
                                <td class="p-4 pl-6">
                                    <div class="font-bold text-foreground flex items-center gap-2">
                                        {{ idx.label }}
                                        <span class="bg-muted border text-muted-foreground font-mono text-[10px] rounded px-1 py-0.5">
                                            {{ idx.index_name }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 font-mono text-xs text-muted-foreground">
                                    {{ idx.model }}
                                </td>
                                <td class="p-4 text-center font-bold font-mono">
                                    {{ idx.db_records_count }}
                                </td>
                                <td class="p-4 text-center font-bold font-mono">
                                    <span :class="{'text-amber-600 dark:text-amber-500': idx.out_of_sync}">
                                        {{ idx.documents_count }}
                                    </span>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col gap-1 items-center max-w-[120px] mx-auto">
                                        <div class="w-full bg-muted rounded-full h-1.5 border overflow-hidden">
                                            <div 
                                                :class="['h-full rounded-full transition-all duration-500', idx.out_of_sync ? 'bg-amber-500' : 'bg-emerald-500']" 
                                                :style="{ width: `${getIndexVisualPercentage(idx)}%` }"
                                            ></div>
                                        </div>
                                        <span class="text-[10px] text-muted-foreground font-mono">{{ getIndexVisualPercentage(idx) }}% khớp</span>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span :class="['inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border', getSyncBadgeClass(idx.sync_status.status)]">
                                            {{ getSyncStatusLabel(idx.sync_status.status) }}
                                        </span>
                                        <span v-if="idx.sync_status.completed_at" class="text-[9px] text-muted-foreground">
                                            {{ idx.sync_status.action === 'import' ? 'Đồng bộ lúc' : 'Xóa lúc' }} 
                                            {{ idx.sync_status.completed_at.split(' ')[1] }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 text-right pr-6">
                                    <div class="flex justify-end gap-2">
                                        <!-- Flush Button -->
                                        <Button
                                            @click="triggerSync(idx.index_name, 'flush')"
                                            :disabled="isSyncing[`${idx.index_name}_flush`] || isSyncing[`${idx.index_name}_import`] || idx.is_indexing || !props.connection.online"
                                            variant="ghost"
                                            size="sm"
                                            class="h-8 text-xs text-rose-600 hover:text-rose-700 dark:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/20 border border-transparent rounded-xl"
                                        >
                                            <Trash2 class="size-3.5" />
                                            Xóa (Flush)
                                        </Button>

                                        <!-- Import Button -->
                                        <Button
                                            @click="triggerSync(idx.index_name, 'import')"
                                            :disabled="isSyncing[`${idx.index_name}_import`] || isSyncing[`${idx.index_name}_flush`] || idx.is_indexing || !props.connection.online"
                                            size="sm"
                                            class="h-8 text-xs rounded-xl gap-1"
                                        >
                                            <Play class="size-3" />
                                            Đồng bộ (Import)
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="localIndexes.length === 0">
                                <td colspan="7" class="p-8 text-center text-muted-foreground italic">
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
            <!-- Latency by Index & Recent Queries -->
            <div class="flex flex-col gap-6">
                <!-- Latency chart -->
                <Card class="flex-1">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-sm font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                            <TrendingUp class="size-4 text-cyan-600 dark:text-cyan-400" />
                            Độ trễ trung bình của các chỉ mục (Latency ms)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pt-5 space-y-4">
                        <div v-for="lat in props.statistics.latency_by_index" :key="lat.index_name" class="space-y-1.5">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-foreground font-mono">{{ lat.index_name }} ({{ lat.total_count }} lượt)</span>
                                <span class="text-cyan-600 dark:text-cyan-400 font-mono font-bold">{{ lat.avg_latency }}ms</span>
                            </div>
                            <div class="w-full bg-muted rounded-full h-3 border overflow-hidden flex">
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
                <Card class="flex-1">
                    <CardHeader class="pb-3 border-b">
                        <CardTitle class="text-sm font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
                            <Clock class="size-4 text-muted-foreground" />
                            Nhật ký lượt truy vấn mới nhất
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto max-h-[300px] overflow-y-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b bg-muted/50 text-[10px] uppercase font-bold text-muted-foreground">
                                        <th class="p-3 pl-5">Từ khóa</th>
                                        <th class="p-3">Phân mục</th>
                                        <th class="p-3 text-center">Độ trễ</th>
                                        <th class="p-3 text-right pr-5">Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-foreground font-mono">
                                    <tr v-for="search in props.statistics.latest_searches" :key="search.created_at" class="hover:bg-muted/30">
                                        <td class="p-3 pl-5 font-semibold text-foreground">"{{ search.keyword }}"</td>
                                        <td class="p-3 text-muted-foreground">{{ search.index_name }}</td>
                                        <td class="p-3 text-center">
                                            <span :class="search.latency_ms < 30 ? 'text-cyan-600 dark:text-cyan-400' : 'text-amber-600 dark:text-amber-500'">
                                                {{ search.latency_ms }}ms
                                            </span>
                                        </td>
                                        <td class="p-3 text-right pr-5 text-muted-foreground text-[10px]">{{ search.created_at }}</td>
                                    </tr>
                                    <tr v-if="props.statistics.latest_searches.length === 0">
                                        <td colspan="4" class="p-6 text-center text-muted-foreground italic font-sans">
                                            Không có nhật ký tìm kiếm gần đây.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Top Search Keywords statistics -->
            <Card class="flex flex-col justify-between">
                <div>
                    <CardHeader class="pb-3 border-b flex flex-row items-center justify-between flex-wrap gap-2">
                        <div>
                            <CardTitle class="text-sm font-bold uppercase tracking-wider text-foreground flex items-center gap-1.5">
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
                            class="h-7 text-[10px] text-muted-foreground hover:text-rose-600 dark:hover:text-rose-400"
                        >
                            <Trash2 class="size-3 mr-1" />
                            Xóa số liệu
                        </Button>
                    </CardHeader>
                    
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b bg-muted/50 text-[10px] uppercase font-bold text-muted-foreground">
                                        <th class="p-3 pl-5 text-center w-12">Hạng</th>
                                        <th class="p-3">Từ khóa (Keyword)</th>
                                        <th class="p-3">Chỉ mục</th>
                                        <th class="p-3 text-center">Số lượt</th>
                                        <th class="p-3 text-right pr-5">Độ trễ trung bình</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y text-foreground font-mono">
                                    <tr v-for="kw in props.statistics.top_keywords" :key="kw.rank" class="hover:bg-muted/30">
                                        <td class="p-3 pl-5 text-center font-bold text-muted-foreground">
                                            <span v-if="kw.rank === 1" class="text-amber-500">🥇 1</span>
                                            <span v-else-if="kw.rank === 2" class="text-zinc-400">🥈 2</span>
                                            <span v-else-if="kw.rank === 3" class="text-amber-700">🥉 3</span>
                                            <span v-else>{{ kw.rank }}</span>
                                        </td>
                                        <td class="p-3 font-semibold text-foreground">"{{ kw.keyword }}"</td>
                                        <td class="p-3 text-muted-foreground">{{ kw.index_name }}</td>
                                        <td class="p-3 text-center font-bold text-violet-600">{{ kw.search_count }}</td>
                                        <td class="p-3 text-right pr-5 text-cyan-600 dark:text-cyan-400 font-bold">{{ kw.avg_latency }}ms</td>
                                    </tr>
                                    <tr v-if="props.statistics.top_keywords.length === 0">
                                        <td colspan="5" class="p-10 text-center text-muted-foreground italic font-sans">
                                            Chưa ghi nhận từ khóa tìm kiếm phổ biến nào...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </div>
                
                <div class="p-4 border-t bg-muted/30 text-xs text-muted-foreground leading-relaxed rounded-b-2xl font-sans">
                    💡 <span class="font-bold text-foreground">Gợi ý tối ưu:</span> Đối với những từ khóa tìm kiếm phổ biến có thời gian phản hồi cao (> 100ms), bạn có thể tối ưu hóa bằng cách cấu hình `searchableAttributes` và `filterableAttributes` tinh gọn trên cài đặt chỉ mục của Meilisearch.
                </div>
            </Card>
        </div>
    </div>
</template>
