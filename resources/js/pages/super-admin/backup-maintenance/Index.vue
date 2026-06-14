<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { 
    Database, 
    HardDrive, 
    Trash2, 
    RefreshCw,
    Download,
    AlertCircle,
    ChevronLeft,
    ChevronRight,
    Play,
    Clock,
    Activity,
    CheckCircle2,
    XCircle,
    FileArchive,
    History,
    ShieldCheck,
    Archive
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface BackupFile {
    filename: string;
    disk: string;
    size: number;
    size_mb: number;
    last_modified: string;
}

interface MaintenanceLog {
    id: number;
    action: string;
    status: string;
    details: any;
    created_at: string;
    operator_name: string;
}

const props = defineProps<{
    backups: BackupFile[];
    logs: {
        data: MaintenanceLog[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        db_name: string;
        db_size_mb: number;
        failed_jobs_count: number;
        expired_sessions_count: number;
        total_audit_logs: number;
        old_audit_logs_count: number;
        is_s3_configured: boolean;
        default_disk: string;
    };
}>();

const backingUp = ref(false);
const optimizing = ref(false);

const selectedOptimizeTasks = ref<string[]>(['cleanup_queues', 'clear_sessions', 'archive_audit_logs']);

function toggleTask(task: string) {
    if (selectedOptimizeTasks.value.includes(task)) {
        selectedOptimizeTasks.value = selectedOptimizeTasks.value.filter(t => t !== task);
    } else {
        selectedOptimizeTasks.value.push(task);
    }
}

function runBackup() {
    backingUp.value = true;
    router.post('/super-admin/backup-maintenance/backup', {}, {
        preserveScroll: true,
        onFinish: () => {
            backingUp.value = false;
        }
    });
}

function runOptimization() {
    if (selectedOptimizeTasks.value.length === 0) {
        alert('Vui lòng chọn ít nhất một tác vụ tối ưu hóa.');
        return;
    }

    if (confirm('Xác nhận kích hoạt các tác vụ tối ưu hóa hệ thống đã chọn? Thao tác dọn dẹp và nén lưu trữ logs có thể mất vài giây.')) {
        optimizing.value = true;
        router.post('/super-admin/backup-maintenance/optimize', {
            actions: selectedOptimizeTasks.value
        }, {
            preserveScroll: true,
            onFinish: () => {
                optimizing.value = false;
            }
        });
    }
}

function deleteBackup(file: BackupFile) {
    if (confirm(`Bạn có chắc chắn muốn xóa tệp sao lưu "${file.filename}" khỏi bộ lưu trữ ${file.disk.toUpperCase()}? Thao tác này không thể phục hồi.`)) {
        router.delete(`/super-admin/backup-maintenance/delete/${file.filename}?disk=${file.disk}`, {
            preserveScroll: true
        });
    }
}

function navigatePage(page: number) {
    router.get('/super-admin/backup-maintenance', { page }, { preserveState: true });
}

function getActionLabel(actionsString: string) {
    return actionsString.split(', ')
        .map(act => {
            switch(act.trim()) {
                case 'cleanup_queues': return 'Dọn dẹp Queue';
                case 'clear_sessions': return 'Xóa Sessions';
                case 'archive_audit_logs': return 'Lưu trữ Audit Logs';
                default: return act;
            }
        })
        .join(', ');
}

function formatDetails(details: any) {
    if (!details) return '';
    
    let parts = [];
    if (details.cleanup_queues) {
        const q = details.cleanup_queues;
        parts.push(`Queue: đã xóa ${q.failed_jobs_deleted} lỗi, ${q.job_batches_deleted} lô`);
    }
    if (details.clear_sessions) {
        const s = details.clear_sessions;
        parts.push(`Session: ${s.db_sessions_deleted} DB, ${s.file_sessions_deleted} file, ${s.expired_tokens_deleted} token`);
    }
    if (details.archive_audit_logs) {
        const a = details.archive_audit_logs;
        if (a.archived_count > 0) {
            parts.push(`Audit Log: Đã lưu trữ ${a.archived_count} dòng sang ${a.disk.toUpperCase()} (${a.archive_file})`);
        } else {
            parts.push('Audit Log: Không có bản ghi > 6 tháng');
        }
    }
    return parts.join(' | ');
}
</script>

<template>
    <Head title="Sao lưu & Tối ưu DB" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="border-b pb-5 border-border/60 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 dark:from-white dark:to-slate-300 bg-clip-text text-transparent">
                        Sao lưu & Tối ưu hóa Cơ sở dữ liệu
                    </h1>
                    <span 
                        v-if="stats.is_s3_configured" 
                        class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"
                    >
                        Cloud Storage S3 Active
                    </span>
                    <span 
                        v-else 
                        class="inline-flex items-center rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400 border border-amber-500/20 animate-pulse"
                        title="Tự động sao lưu cục bộ vì S3/MinIO chưa cấu hình"
                    >
                        Chế độ Local Storage (No S3)
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Quản lý sao lưu dữ liệu thủ công / tự động, dọn dẹp tài nguyên thừa, và nén lưu trữ nhật ký Audit Logs cũ hơn 6 tháng sang ổ đĩa thứ cấp.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <Button 
                    variant="outline" 
                    size="sm"
                    class="gap-1.5 cursor-pointer"
                    @click="router.reload({ preserveScroll: true })"
                >
                    <RefreshCw class="size-3.5" /> Tải lại dữ liệu
                </Button>
                <Button 
                    variant="default" 
                    size="sm"
                    class="gap-1.5 cursor-pointer bg-indigo-600 hover:bg-indigo-700 text-white shadow-md rounded-xl"
                    @click="runBackup"
                    :disabled="backingUp"
                >
                    <Database class="size-3.5" :class="{ 'animate-spin': backingUp }" />
                    {{ backingUp ? 'Đang sao lưu...' : 'Kích hoạt sao lưu' }}
                </Button>
            </div>
        </div>

        <!-- Stats Overview Widgets -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <Card class="relative overflow-hidden border-border bg-card/60 backdrop-blur-md shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Cơ sở dữ liệu chính</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-foreground font-mono">{{ stats.db_size_mb }}</span>
                        <span class="text-xs text-muted-foreground">MB ({{ stats.db_name }})</span>
                    </div>
                    <Database class="absolute right-4 bottom-4 size-10 text-slate-200 dark:text-slate-800/40 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border-border bg-card/60 backdrop-blur-md shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Hàng đợi lỗi cần xóa</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold font-mono" :class="stats.failed_jobs_count > 0 ? 'text-amber-600' : 'text-emerald-600'">{{ stats.failed_jobs_count }}</span>
                        <span class="text-xs text-muted-foreground">jobs lỗi</span>
                    </div>
                    <Activity class="absolute right-4 bottom-4 size-10 text-slate-200 dark:text-slate-800/40 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border-border bg-card/60 backdrop-blur-md shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Session hết hạn</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-foreground font-mono">{{ stats.expired_sessions_count }}</span>
                        <span class="text-xs text-muted-foreground">phiên hết hạn</span>
                    </div>
                    <Clock class="absolute right-4 bottom-4 size-10 text-slate-200 dark:text-slate-800/40 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border-border bg-card/60 backdrop-blur-md shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Audit Log cũ (>6 tháng)</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold font-mono" :class="stats.old_audit_logs_count > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600'">
                            {{ stats.old_audit_logs_count }}
                        </span>
                        <span class="text-xs text-muted-foreground">/ {{ stats.total_audit_logs }} bản ghi</span>
                    </div>
                    <FileArchive class="absolute right-4 bottom-4 size-10 text-slate-200 dark:text-slate-800/40 pointer-events-none" />
                </CardContent>
            </Card>
        </div>

        <!-- Warning local storage alert -->
        <div v-if="!stats.is_s3_configured" class="flex items-start gap-3 rounded-2xl border border-amber-200/60 bg-amber-500/10 p-4 text-amber-800 dark:text-amber-300">
            <AlertCircle class="size-5 text-amber-500 shrink-0 mt-0.5" />
            <div>
                <h4 class="font-bold text-sm">Cảnh báo: S3 Cloud Storage Chưa cấu hình</h4>
                <p class="text-xs mt-0.5">
                    Hệ thống sẽ lưu trữ tạm thời các tệp sao lưu `.sql.gz` và tệp lưu trữ `.json.gz` của Audit Logs vào ổ đĩa máy chủ cục bộ (`storage/app/private`). Để đảm bảo an toàn thảm họa, vui lòng thiết lập các tham số `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, và `AWS_BUCKET` trong cấu hình môi trường (.env).
                </p>
            </div>
        </div>

        <!-- Main Workspace Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Side: Backup List & Run Optimization (7 columns) -->
            <div class="lg:col-span-7 flex flex-col gap-6">
                <!-- Backup Manager Card -->
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <FileArchive class="size-5 text-indigo-600" />
                            Quản lý các bản Sao lưu (Database Backups)
                        </CardTitle>
                        <CardDescription>
                            Tải về hoặc xóa các bản sao lưu tự động định kỳ dạng `.sql.gz`. Bộ nhớ mặc định: <span class="font-bold">{{ stats.default_disk }}</span>.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="backups.length === 0" class="flex flex-col items-center justify-center py-12 text-muted-foreground/60">
                            <div class="rounded-full bg-muted p-4 mb-3">
                                <Database class="h-8 w-8 text-slate-400" />
                            </div>
                            <h3 class="font-bold text-foreground">Chưa có bản sao lưu nào</h3>
                            <p class="text-xs mt-1 text-center max-w-xs">Nhấn nút "Kích hoạt sao lưu" ở góc trên bên phải để tạo bản sao lưu đầu tiên.</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-sm">
                                <thead>
                                    <tr class="border-b bg-muted/40 text-muted-foreground font-semibold text-xs uppercase">
                                        <th class="py-3 px-4">Tên tệp tin sao lưu</th>
                                        <th class="py-3 px-4">Lưu trữ</th>
                                        <th class="py-3 px-4">Kích thước</th>
                                        <th class="py-3 px-4">Ngày tạo</th>
                                        <th class="py-3 px-4 text-right">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/60">
                                    <tr v-for="file in backups" :key="file.filename" class="hover:bg-muted/30 transition-colors group">
                                        <td class="py-3 px-4 font-medium text-foreground truncate max-w-xs" :title="file.filename">
                                            {{ file.filename }}
                                        </td>
                                        <td class="py-3 px-4">
                                            <span 
                                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold uppercase border"
                                                :class="file.disk === 's3' 
                                                    ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' 
                                                    : 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700'"
                                            >
                                                {{ file.disk }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 font-mono text-xs">
                                            {{ file.size_mb }} MB
                                        </td>
                                        <td class="py-3 px-4 text-xs text-muted-foreground">
                                            {{ file.last_modified }}
                                        </td>
                                        <td class="py-3 px-4 text-right flex items-center justify-end gap-1.5">
                                            <a 
                                                :href="`/super-admin/backup-maintenance/download/${file.filename}?disk=${file.disk}`"
                                                class="inline-flex items-center justify-center p-2 text-indigo-600 hover:bg-indigo-500/10 rounded-lg transition-colors cursor-pointer"
                                                title="Tải về bản sao lưu"
                                            >
                                                <Download class="size-4" />
                                            </a>
                                            <button 
                                                @click="deleteBackup(file)" 
                                                class="p-2 text-rose-500 hover:bg-rose-500/10 rounded-lg transition-colors cursor-pointer"
                                                title="Xóa bản sao lưu"
                                            >
                                                <Trash2 class="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Optimization Panel Card -->
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <Activity class="size-5 text-indigo-600" />
                            Kích hoạt Tối ưu hóa Định kỳ (DB Maintenance)
                        </CardTitle>
                        <CardDescription>
                            Chủ động dọn dẹp các bản ghi rác để cải thiện tốc độ truy vấn SQL chính.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-3">
                            <!-- Task 1 -->
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-border bg-muted/20 hover:bg-muted/40 transition-colors cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedOptimizeTasks.includes('cleanup_queues')" 
                                    @change="toggleTask('cleanup_queues')"
                                    class="size-4.5 rounded text-indigo-600 border-border mt-0.5 accent-indigo-600"
                                />
                                <div>
                                    <h4 class="font-semibold text-sm text-foreground">Dọn dẹp hàng đợi cũ (Cleanup Queues)</h4>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        Xóa tất cả các tác vụ hàng đợi bị lỗi ({{ stats.failed_jobs_count }} failed jobs) và lịch sử các lô công việc đã chạy hoàn tất.
                                    </p>
                                </div>
                            </label>

                            <!-- Task 2 -->
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-border bg-muted/20 hover:bg-muted/40 transition-colors cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedOptimizeTasks.includes('clear_sessions')" 
                                    @change="toggleTask('clear_sessions')"
                                    class="size-4.5 rounded text-indigo-600 border-border mt-0.5 accent-indigo-600"
                                />
                                <div>
                                    <h4 class="font-semibold text-sm text-foreground">Xóa lịch sử đăng nhập/session hết hạn (Clear Sessions)</h4>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        Purge các phiên session hết hạn (hiện tại có {{ stats.expired_sessions_count }} phiên) trong Database / Storage và các mã token cá nhân đã quá hạn.
                                    </p>
                                </div>
                            </label>

                            <!-- Task 3 -->
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-border bg-muted/20 hover:bg-muted/40 transition-colors cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedOptimizeTasks.includes('archive_audit_logs')" 
                                    @change="toggleTask('archive_audit_logs')"
                                    class="size-4.5 rounded text-indigo-600 border-border mt-0.5 accent-indigo-600"
                                />
                                <div>
                                    <h4 class="font-semibold text-sm text-foreground flex items-center gap-1.5">
                                        Lưu trữ & xóa Audit Logs cũ hơn 6 tháng (Archive Audit Logs)
                                        <span class="inline-flex rounded bg-rose-500/10 px-1.5 py-0.2 text-[10px] font-medium text-rose-600 dark:text-rose-400">Tối ưu dung lượng</span>
                                    </h4>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        Di chuyển {{ stats.old_audit_logs_count }} bản ghi nhật ký hoạt động cũ hơn 6 tháng (trên tổng số {{ stats.total_audit_logs }}) đóng gói thành tệp nén `.json.gz` gửi lên S3/MinIO và xóa khỏi DB chính.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <Button 
                                variant="default"
                                class="w-full md:w-auto bg-emerald-600 hover:bg-emerald-700 text-white shadow-md rounded-xl cursor-pointer font-medium"
                                @click="runOptimization"
                                :disabled="optimizing || selectedOptimizeTasks.length === 0"
                            >
                                <Play class="size-4 mr-1.5" :class="{ 'animate-pulse': optimizing }" />
                                {{ optimizing ? 'Đang chạy tối ưu...' : 'Bắt đầu tối ưu hóa ngay' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Side: DB Maintenance Logs History (5 columns) -->
            <div class="lg:col-span-5 flex flex-col">
                <Card class="border-border bg-card shadow-xs flex-1 flex flex-col">
                    <CardHeader>
                        <CardTitle class="text-lg flex items-center gap-2">
                            <History class="size-5 text-indigo-600" />
                            Lịch sử Tối ưu hóa (Maintenance Logs)
                        </CardTitle>
                        <CardDescription>
                            Theo dõi kết quả thực thi các tác vụ dọn dẹp và lưu trữ dữ liệu.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0 flex-1 flex flex-col justify-between">
                        <div v-if="logs.data.length === 0" class="flex flex-col items-center justify-center py-20 text-muted-foreground/60">
                            <div class="rounded-full bg-muted p-4 mb-3">
                                <History class="h-8 w-8 text-slate-400" />
                            </div>
                            <h3 class="font-bold text-foreground">Chưa có lịch sử tối ưu</h3>
                            <p class="text-xs mt-1 text-center max-w-xs">Hệ thống chưa chạy bất kỳ tác vụ tối ưu hóa thủ công hoặc tự động nào.</p>
                        </div>

                        <div v-else class="flex-1 flex flex-col justify-between">
                            <div class="divide-y divide-border/60">
                                <div 
                                    v-for="log in logs.data" 
                                    :key="log.id"
                                    class="p-4 hover:bg-muted/10 transition-colors"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-sm text-foreground">
                                                {{ getActionLabel(log.action) }}
                                            </p>
                                            <p class="text-[11px] text-muted-foreground mt-0.5">
                                                Thực hiện bởi: <span class="font-medium text-foreground">{{ log.operator_name }}</span> · {{ log.created_at }}
                                            </p>
                                        </div>
                                        <span 
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold border"
                                            :class="log.status === 'success' 
                                                ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' 
                                                : 'bg-rose-500/10 text-rose-600 border-rose-500/20'"
                                        >
                                            <CheckCircle2 v-if="log.status === 'success'" class="size-3 shrink-0" />
                                            <XCircle v-else class="size-3 shrink-0" />
                                            {{ log.status === 'success' ? 'Thành công' : 'Thất bại' }}
                                        </span>
                                    </div>
                                    <div class="mt-2 bg-muted/40 dark:bg-zinc-900 border border-border/40 rounded-lg p-2.5 text-xs font-mono text-muted-foreground leading-relaxed break-all">
                                        {{ formatDetails(log.details) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-border/80 px-4 py-3 bg-muted/10">
                                <span class="text-xs text-muted-foreground">
                                    {{ logs.current_page }}/{{ logs.last_page }} · Tổng {{ logs.total }} dòng
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        :disabled="logs.current_page <= 1"
                                        @click="navigatePage(logs.current_page - 1)"
                                        class="cursor-pointer size-8 p-0"
                                    >
                                        <ChevronLeft class="size-4" />
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        :disabled="logs.current_page >= logs.last_page"
                                        @click="navigatePage(logs.current_page + 1)"
                                        class="cursor-pointer size-8 p-0"
                                    >
                                        <ChevronRight class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
