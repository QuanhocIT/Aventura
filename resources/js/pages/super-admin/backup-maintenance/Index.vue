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
import { PageHeader, TerminalCard, StatCard, StatusBadge, LedIndicator, EmptyState } from '@/components/super-admin';
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
    if (!details) {
return '';
}
    
    const parts = [];

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

const dbHealthGrade = computed(() => {
    let score = 100;
    if (props.stats.failed_jobs_count > 0) score -= 20;
    if (props.stats.expired_sessions_count > 50) score -= 15;
    if (props.stats.old_audit_logs_count > 0) score -= 20;
    if (!props.stats.is_s3_configured) score -= 15;
    
    if (score >= 90) return { grade: 'A+', color: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20', label: 'Tối ưu', desc: 'Cơ sở dữ liệu đang ở trạng thái sạch sẽ và hoạt động với hiệu suất tối đa.' };
    if (score >= 70) return { grade: 'B', color: 'text-amber-500 bg-amber-500/10 border-amber-500/20', label: 'Cần chú ý', desc: 'Phát hiện một số tài nguyên thừa hoặc cấu hình lưu trữ chưa tối ưu.' };
    return { grade: 'C', color: 'text-rose-500 bg-rose-500/10 border-rose-500/20', label: 'Cần tối ưu', desc: 'Cơ sở dữ liệu đang có dung lượng rác tích tụ lớn hoặc thiếu cấu hình sao lưu an toàn.' };
});

const s3Advice = computed(() => {
    if (!props.stats.is_s3_configured) {
        return { status: 'warning', text: 'Cấu hình S3 chưa hoạt động. Các bản sao lưu chỉ được lưu cục bộ trên máy chủ này, có nguy cơ mất dữ liệu cao.' };
    }
    return { status: 'ok', text: 'S3 Cloud Storage hoạt động tốt. Bản sao lưu được phân tán an toàn.' };
});

const failedJobsAdvice = computed(() => {
    if (props.stats.failed_jobs_count > 0) {
        return { status: 'warning', text: `Phát hiện ${props.stats.failed_jobs_count} jobs hàng đợi lỗi chưa xóa. Kích hoạt dọn dẹp hàng đợi để giải phóng DB.` };
    }
    return { status: 'ok', text: 'Hàng đợi lỗi sạch sẽ.' };
});

const sessionsAdvice = computed(() => {
    if (props.stats.expired_sessions_count > 100) {
        return { status: 'warning', text: `Phát hiện ${props.stats.expired_sessions_count} session hết hạn. Kích hoạt xóa session để tăng tốc truy vấn đăng nhập.` };
    }
    return { status: 'ok', text: 'Không có session hết hạn tích tụ.' };
});

const auditLogsAdvice = computed(() => {
    if (props.stats.old_audit_logs_count > 0) {
        return { status: 'warning', text: `Có ${props.stats.old_audit_logs_count} dòng Audit Logs cũ (> 6 tháng). Nên kích hoạt dọn dẹp & nén gửi lên S3.` };
    }
    return { status: 'ok', text: 'Các Audit Logs cũ đã được giải phóng sạch sẽ khỏi DB chính.' };
});

const oldLogsPercentage = computed(() => {
    if (!props.stats.total_audit_logs) return 0;
    return Math.round((props.stats.old_audit_logs_count / props.stats.total_audit_logs) * 100);
});

const hasHealthWarnings = computed(() => {
    return !props.stats.is_s3_configured || 
           props.stats.failed_jobs_count > 0 || 
           props.stats.expired_sessions_count > 100 || 
           props.stats.old_audit_logs_count > 0;
});
</script>

<template>
    <Head title="Sao lưu & Tối ưu DB" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Sao lưu & Tối ưu hóa Cơ sở dữ liệu"
            subtitle="Quản lý sao lưu dữ liệu, dọn dẹp tài nguyên thừa, và nén lưu trữ nhật ký Audit Logs cũ."
            :icon="Database"
        >
            <template #actions>
                <LedIndicator v-if="stats.is_s3_configured" status="online" label="Cloud S3 Active" />
                <LedIndicator v-else status="warning" label="Local Storage" />
            </template>
        </PageHeader>

        <div class="flex items-center justify-end gap-3">
            <Button 
                variant="outline" 
                size="sm"
                class="gap-1.5 rounded-xl border-border hover:bg-muted font-bold text-xs h-9 cursor-pointer"
                @click="router.reload({ preserveScroll: true })"
            >
                <RefreshCw class="size-3.5" /> Tải lại dữ liệu
            </Button>
            <Button 
                variant="default" 
                size="sm"
                class="gap-1.5 cursor-pointer bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white shadow-xs rounded-xl font-bold text-xs h-9"
                @click="runBackup"
                :disabled="backingUp"
            >
                <Database class="size-3.5" :class="{ 'animate-spin': backingUp }" />
                {{ backingUp ? 'Đang sao lưu...' : 'Kích hoạt sao lưu' }}
            </Button>
        </div>

        <!-- Stats Overview Widgets -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardHeader class="pb-2">
                    <CardTitle class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Cơ sở dữ liệu chính</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-800 dark:text-slate-200 font-mono">{{ stats.db_size_mb }}</span>
                        <span class="text-xs text-muted-foreground font-semibold">MB ({{ stats.db_name }})</span>
                    </div>
                    <Database class="absolute right-4 bottom-4 size-10 text-orange-500/10 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardHeader class="pb-2">
                    <CardTitle class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Hàng đợi lỗi cần xóa</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold font-mono" :class="stats.failed_jobs_count > 0 ? 'text-amber-500' : 'text-emerald-500'">{{ stats.failed_jobs_count }}</span>
                        <span class="text-xs text-muted-foreground font-semibold">jobs lỗi</span>
                    </div>
                    <Activity class="absolute right-4 bottom-4 size-10 text-amber-500/10 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardHeader class="pb-2">
                    <CardTitle class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Session hết hạn</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-800 dark:text-slate-200 font-mono">{{ stats.expired_sessions_count }}</span>
                        <span class="text-xs text-muted-foreground font-semibold">phiên hết hạn</span>
                    </div>
                    <Clock class="absolute right-4 bottom-4 size-10 text-slate-500/10 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardHeader class="pb-2">
                    <CardTitle class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Audit Log cũ (>6 tháng)</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold font-mono" :class="stats.old_audit_logs_count > 0 ? 'text-rose-500 dark:text-rose-450' : 'text-emerald-500'">
                            {{ stats.old_audit_logs_count }}
                        </span>
                        <span class="text-xs text-muted-foreground font-semibold">/ {{ stats.total_audit_logs }} bản ghi</span>
                    </div>
                    <FileArchive class="absolute right-4 bottom-4 size-10 text-rose-500/10 pointer-events-none" />
                </CardContent>
            </Card>
        </div>

        <!-- Warning local storage alert -->
        <div v-if="!stats.is_s3_configured" class="flex items-start gap-3 rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-4 text-amber-800 dark:text-amber-300 backdrop-blur-md">
            <AlertCircle class="size-5 text-amber-500 shrink-0 mt-0.5" />
            <div>
                <h4 class="font-bold text-xs uppercase tracking-wider">Cảnh báo: S3 Cloud Storage Chưa cấu hình</h4>
                <p class="text-xs mt-1 font-medium leading-relaxed">
                    Hệ thống sẽ lưu trữ tạm thời các tệp sao lưu `.sql.gz` và tệp lưu trữ `.json.gz` của Audit Logs vào ổ đĩa máy chủ cục bộ (`storage/app/private`). Để đảm bảo an toàn thảm họa, vui lòng thiết lập các tham số `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, và `AWS_BUCKET` trong cấu hình môi trường (.env).
                </p>
            </div>
        </div>

        <!-- Main Workspace Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Side: Backup List & Run Optimization (8 columns) -->
            <div class="lg:col-span-8 flex flex-col gap-6">
                <!-- Backup Manager Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardHeader class="border-b border-border/40 bg-muted/10 p-5">
                        <CardTitle class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <FileArchive class="size-5 text-orange-500" />
                            Quản lý các bản Sao lưu (Database Backups)
                        </CardTitle>
                        <CardDescription class="text-xs text-muted-foreground font-semibold mt-1">
                            Tải về hoặc xóa các bản sao lưu tự động định kỳ dạng `.sql.gz`. Bộ nhớ mặc định: <span class="font-bold text-orange-500">{{ stats.default_disk.toUpperCase() }}</span>.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="backups.length === 0" class="flex flex-col items-center justify-center py-12 text-muted-foreground/60">
                            <div class="rounded-full bg-muted p-4 mb-3">
                                <Database class="h-8 w-8 text-slate-400" />
                            </div>
                            <h3 class="font-bold text-foreground text-xs uppercase tracking-wider">Chưa có bản sao lưu nào</h3>
                            <p class="text-[10px] mt-1 text-center max-w-xs font-semibold text-muted-foreground">Nhấn nút "Kích hoạt sao lưu" ở góc trên bên phải để tạo bản sao lưu đầu tiên.</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr class="border-b border-border/45 bg-muted/20 text-muted-foreground font-bold text-[10px] uppercase tracking-wider">
                                        <th class="py-3.5 px-5">Tên tệp tin sao lưu</th>
                                        <th class="py-3.5 px-5">Lưu trữ</th>
                                        <th class="py-3.5 px-5">Kích thước</th>
                                        <th class="py-3.5 px-5">Ngày tạo</th>
                                        <th class="py-3.5 px-5 text-right">Hành động</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/25">
                                    <tr v-for="file in backups" :key="file.filename" class="hover:bg-muted/15 transition-colors group">
                                        <td class="py-3 px-5 font-semibold text-slate-700 dark:text-slate-350 truncate max-w-xs" :title="file.filename">
                                            {{ file.filename }}
                                        </td>
                                        <td class="py-3 px-5">
                                            <span 
                                                class="inline-flex items-center rounded-lg px-2 py-0.5 text-[9px] font-bold uppercase border"
                                                :class="file.disk === 's3' 
                                                    ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' 
                                                    : 'bg-orange-500/10 text-orange-600 border-orange-500/20'"
                                            >
                                                {{ file.disk }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-5 font-mono text-[10px] font-bold">
                                            {{ file.size_mb }} MB
                                        </td>
                                        <td class="py-3 px-5 text-[10px] text-muted-foreground font-semibold">
                                            {{ file.last_modified }}
                                        </td>
                                        <td class="py-3 px-5 text-right flex items-center justify-end gap-1.5">
                                            <a 
                                                :href="`/super-admin/backup-maintenance/download/${file.filename}?disk=${file.disk}`"
                                                class="inline-flex items-center justify-center size-8 text-orange-500 hover:bg-orange-500/10 rounded-lg transition-colors cursor-pointer"
                                                title="Tải về bản sao lưu"
                                            >
                                                <Download class="size-4" />
                                            </a>
                                            <button 
                                                @click="deleteBackup(file)" 
                                                class="size-8 flex items-center justify-center text-rose-500 hover:bg-rose-500/10 rounded-lg transition-colors cursor-pointer"
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
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardHeader class="border-b border-border/40 bg-muted/10 p-5">
                        <CardTitle class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <Activity class="size-5 text-orange-500" />
                            Kích hoạt Tối ưu hóa Định kỳ (DB Maintenance)
                        </CardTitle>
                        <CardDescription class="text-xs text-muted-foreground font-semibold mt-1">
                            Chủ động dọn dẹp các bản ghi rác để cải thiện tốc độ truy vấn SQL chính.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-5 space-y-4">
                        <div class="space-y-3">
                            <!-- Task 1 -->
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border border-border/30 bg-muted/10 hover:bg-muted/20 transition-all cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedOptimizeTasks.includes('cleanup_queues')" 
                                    @change="toggleTask('cleanup_queues')"
                                    class="size-4.5 rounded text-orange-500 border-border mt-0.5 accent-orange-500 focus:ring-orange-500/20"
                                />
                                <div>
                                    <h4 class="font-bold text-xs text-slate-800 dark:text-slate-200">Dọn dẹp hàng đợi cũ (Cleanup Queues)</h4>
                                    <p class="text-[10px] text-muted-foreground mt-0.5 leading-normal font-semibold">
                                        Xóa tất cả các tác vụ hàng đợi bị lỗi ({{ stats.failed_jobs_count }} failed jobs) và lịch sử các lô công việc đã chạy hoàn tất.
                                    </p>
                                </div>
                            </label>

                            <!-- Task 2 -->
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border border-border/30 bg-muted/10 hover:bg-muted/20 transition-all cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedOptimizeTasks.includes('clear_sessions')" 
                                    @change="toggleTask('clear_sessions')"
                                    class="size-4.5 rounded text-orange-500 border-border mt-0.5 accent-orange-500 focus:ring-orange-500/20"
                                />
                                <div>
                                    <h4 class="font-bold text-xs text-slate-800 dark:text-slate-200">Xóa lịch sử đăng nhập/session hết hạn (Clear Sessions)</h4>
                                    <p class="text-[10px] text-muted-foreground mt-0.5 leading-normal font-semibold">
                                        Purge các phiên session hết hạn (hiện tại có {{ stats.expired_sessions_count }} phiên) trong Database / Storage và các mã token cá nhân đã quá hạn.
                                    </p>
                                </div>
                            </label>

                            <!-- Task 3 -->
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border border-border/30 bg-muted/10 hover:bg-muted/20 transition-all cursor-pointer">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedOptimizeTasks.includes('archive_audit_logs')" 
                                    @change="toggleTask('archive_audit_logs')"
                                    class="size-4.5 rounded text-orange-500 border-border mt-0.5 accent-orange-500 focus:ring-orange-500/20"
                                />
                                <div>
                                    <h4 class="font-bold text-xs text-slate-800 dark:text-slate-200 flex items-center gap-1.5 flex-wrap">
                                        Lưu trữ & xóa Audit Logs cũ hơn 6 tháng (Archive Audit Logs)
                                        <span class="inline-flex rounded bg-rose-500/10 px-1.5 py-0.2 text-[9px] font-bold text-rose-600 dark:text-rose-450 border border-rose-500/10">Tối ưu dung lượng</span>
                                    </h4>
                                    <p class="text-[10px] text-muted-foreground mt-0.5 leading-normal font-semibold">
                                        Di chuyển {{ stats.old_audit_logs_count }} bản ghi nhật ký hoạt động cũ hơn 6 tháng (trên tổng số {{ stats.total_audit_logs }}) đóng gói thành tệp nén `.json.gz` gửi lên S3/MinIO và xóa khỏi DB chính.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="pt-2 flex justify-end">
                            <Button 
                                variant="default"
                                class="w-full md:w-auto bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-white shadow-xs rounded-xl cursor-pointer font-bold text-xs h-10 px-6 flex items-center gap-1.5"
                                @click="runOptimization"
                                :disabled="optimizing || selectedOptimizeTasks.length === 0"
                            >
                                <Play class="size-4" :class="{ 'animate-pulse': optimizing }" />
                                {{ optimizing ? 'Đang chạy tối ưu...' : 'Bắt đầu tối ưu hóa ngay' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Side: AI DB Coach & Historical Logs (4 columns) -->
            <div class="lg:col-span-4 flex flex-col gap-6">
                <!-- AI DB Coach Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardContent class="p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-border/40 pb-3">
                            <h4 class="text-xs font-black text-muted-foreground uppercase tracking-wider">AI Database Health Coach</h4>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase border animate-pulse"
                                :class="hasHealthWarnings ? 'bg-amber-500/10 text-amber-600 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'"
                            >
                                {{ hasHealthWarnings ? 'Cần chú ý' : 'Khỏe mạnh' }}
                            </span>
                        </div>

                        <!-- Score Rank Layout -->
                        <div class="flex items-center gap-4 bg-muted/15 border border-border/30 rounded-2xl p-4">
                            <div class="size-14 rounded-xl border flex items-center justify-center text-xl font-black shadow-xs shrink-0"
                                :class="dbHealthGrade.color"
                            >
                                {{ dbHealthGrade.grade }}
                            </div>
                            <div>
                                <h5 class="text-xs font-black text-slate-800 dark:text-slate-200">Trạng thái: {{ dbHealthGrade.label }}</h5>
                                <p class="text-[10px] text-muted-foreground font-semibold mt-0.5 leading-normal">{{ dbHealthGrade.desc }}</p>
                            </div>
                        </div>

                        <!-- Audit Logs Age Distribution Chart -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground">
                                <span>Phân bổ lưu trữ Audit Logs</span>
                                <span>{{ oldLogsPercentage }}% Logs Cũ (>6th)</span>
                            </div>
                            <!-- Progress Bar -->
                            <div class="h-2 w-full bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden flex">
                                <div class="h-full bg-rose-500" :style="{ width: oldLogsPercentage + '%' }" title="Logs cũ cần đóng gói"></div>
                                <div class="h-full bg-emerald-500 flex-1" title="Logs hoạt động bình thường"></div>
                            </div>
                            <div class="flex items-center justify-between text-[9px] text-muted-foreground font-semibold">
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-rose-500 block"></span> Cần đóng gói: {{ stats.old_audit_logs_count }}</span>
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500 block"></span> Khớp hoạt động: {{ stats.total_audit_logs - stats.old_audit_logs_count }}</span>
                            </div>
                        </div>

                        <div class="space-y-2.5 border-t border-border/40 pt-3">
                            <h5 class="text-[10px] font-black text-slate-700 dark:text-slate-350 uppercase tracking-wider">Khuyến nghị chẩn đoán:</h5>
                            
                            <!-- Advice 1 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div class="mt-0.5 shrink-0" :class="s3Advice.status === 'warning' ? 'text-amber-500' : 'text-emerald-500'">
                                    <AlertCircle v-if="s3Advice.status === 'warning'" class="size-3.5" />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p class="font-semibold text-slate-600 dark:text-slate-400 leading-normal">{{ s3Advice.text }}</p>
                            </div>

                            <!-- Advice 2 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div class="mt-0.5 shrink-0" :class="failedJobsAdvice.status === 'warning' ? 'text-amber-500' : 'text-emerald-500'">
                                    <AlertCircle v-if="failedJobsAdvice.status === 'warning'" class="size-3.5" />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p class="font-semibold text-slate-600 dark:text-slate-400 leading-normal">{{ failedJobsAdvice.text }}</p>
                            </div>

                            <!-- Advice 3 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div class="mt-0.5 shrink-0" :class="sessionsAdvice.status === 'warning' ? 'text-amber-500' : 'text-emerald-500'">
                                    <AlertCircle v-if="sessionsAdvice.status === 'warning'" class="size-3.5" />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p class="font-semibold text-slate-600 dark:text-slate-400 leading-normal">{{ sessionsAdvice.text }}</p>
                            </div>

                            <!-- Advice 4 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div class="mt-0.5 shrink-0" :class="auditLogsAdvice.status === 'warning' ? 'text-amber-500' : 'text-emerald-500'">
                                    <AlertCircle v-if="auditLogsAdvice.status === 'warning'" class="size-3.5" />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p class="font-semibold text-slate-600 dark:text-slate-400 leading-normal">{{ auditLogsAdvice.text }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- DB Maintenance Logs Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl flex-1 flex flex-col">
                    <CardHeader class="border-b border-border/40 bg-muted/10 p-5">
                        <CardTitle class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <History class="size-5 text-orange-500" />
                            Lịch sử Tối ưu hóa (Maintenance Logs)
                        </CardTitle>
                        <CardDescription class="text-xs text-muted-foreground font-semibold mt-1">
                            Theo dõi kết quả thực thi các tác vụ dọn dẹp và lưu trữ dữ liệu.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0 flex-1 flex flex-col justify-between">
                        <div v-if="logs.data.length === 0" class="flex flex-col items-center justify-center py-20 text-muted-foreground/60">
                            <div class="rounded-full bg-muted p-4 mb-3">
                                <History class="h-8 w-8 text-slate-400" />
                            </div>
                            <h3 class="font-bold text-foreground text-xs uppercase tracking-wider">Chưa có lịch sử tối ưu</h3>
                            <p class="text-[10px] mt-1 text-center max-w-xs font-semibold text-muted-foreground">Hệ thống chưa chạy bất kỳ tác vụ tối ưu hóa thủ công hoặc tự động nào.</p>
                        </div>

                        <div v-else class="flex-1 flex flex-col justify-between">
                            <div class="divide-y divide-border/20">
                                <div 
                                    v-for="log in logs.data" 
                                    :key="log.id"
                                    class="p-4 hover:bg-muted/10 transition-colors"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-bold text-xs text-slate-800 dark:text-slate-200">
                                                {{ getActionLabel(log.action) }}
                                            </p>
                                            <p class="text-[10px] text-muted-foreground font-semibold mt-0.5">
                                                Thực hiện: <span class="text-foreground">{{ log.operator_name }}</span> · {{ log.created_at }}
                                            </p>
                                        </div>
                                        <span 
                                            class="inline-flex items-center gap-1 rounded-lg px-2 py-0.5 text-[9px] font-bold border"
                                            :class="log.status === 'success' 
                                                ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' 
                                                : 'bg-rose-500/10 text-rose-600 border-rose-500/20'"
                                        >
                                            <CheckCircle2 v-if="log.status === 'success'" class="size-3 shrink-0" />
                                            <XCircle v-else class="size-3 shrink-0" />
                                            {{ log.status === 'success' ? 'Thành công' : 'Thất bại' }}
                                        </span>
                                    </div>
                                    <div class="mt-2 bg-background dark:bg-slate-900 border border-border/40 rounded-lg p-2.5 text-[10px] font-mono text-muted-foreground leading-relaxed break-all font-semibold">
                                        {{ formatDetails(log.details) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-border/20 px-4 py-3 bg-muted/10">
                                <span class="text-[10px] text-muted-foreground font-semibold">
                                    Trang {{ logs.current_page }}/{{ logs.last_page }} · Tổng {{ logs.total }} dòng
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        :disabled="logs.current_page <= 1"
                                        @click="navigatePage(logs.current_page - 1)"
                                        class="cursor-pointer size-8 p-0 rounded-lg border-border"
                                    >
                                        <ChevronLeft class="size-4" />
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        :disabled="logs.current_page >= logs.last_page"
                                        @click="navigatePage(logs.current_page + 1)"
                                        class="cursor-pointer size-8 p-0 rounded-lg border-border"
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
