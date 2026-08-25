<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Database, Trash2, RefreshCw, Download, AlertCircle, ChevronLeft, ChevronRight, Play, Clock, Activity, CheckCircle2, XCircle, FileArchive, History } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import {
    PageHeader,
    LedIndicator,
} from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { confirmDialog } from '@/composables/useConfirm';
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

const selectedOptimizeTasks = ref<string[]>([
    'cleanup_queues',
    'clear_sessions',
    'archive_audit_logs',
]);

function toggleTask(task: string) {
    if (selectedOptimizeTasks.value.includes(task)) {
        selectedOptimizeTasks.value = selectedOptimizeTasks.value.filter(
            (t) => t !== task,
        );
    } else {
        selectedOptimizeTasks.value.push(task);
    }
}

function runBackup() {
    backingUp.value = true;
    router.post(
        '/super-admin/backup-maintenance/backup',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                backingUp.value = false;
            },
        },
    );
}

async function runOptimization() {
    if (selectedOptimizeTasks.value.length === 0) {
        toast.error('Vui lòng chọn ít nhất một tác vụ tối ưu hóa.');

        return;
    }

    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Xác nhận kích hoạt các tác vụ tối ưu hóa hệ thống đã chọn? Thao tác dọn dẹp và nén lưu trữ logs có thể mất vài giây.',
            variant: 'default',
        })
    ) {
        optimizing.value = true;
        router.post(
            '/super-admin/backup-maintenance/optimize',
            {
                actions: selectedOptimizeTasks.value,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    optimizing.value = false;
                },
            },
        );
    }
}

async function deleteBackup(file: BackupFile) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn xóa tệp sao lưu "${file.filename}" khỏi bộ lưu trữ ${file.disk.toUpperCase()}? Thao tác này không thể phục hồi.`,
        })
    ) {
        router.delete(
            `/super-admin/backup-maintenance/delete/${file.filename}?disk=${file.disk}`,
            {
                preserveScroll: true,
            },
        );
    }
}

function navigatePage(page: number) {
    router.get(
        '/super-admin/backup-maintenance',
        { page },
        { preserveState: true },
    );
}

function getActionLabel(actionsString: string) {
    return actionsString
        .split(', ')
        .map((act) => {
            switch (act.trim()) {
                case 'cleanup_queues':
                    return 'Dọn dẹp hàng đợi';
                case 'clear_sessions':
                    return 'Xóa phiên';
                case 'archive_audit_logs':
                    return 'Lưu trữ nhật ký kiểm toán';
                default:
                    return act;
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
        parts.push(
            `Hàng đợi: đã xóa ${q.failed_jobs_deleted} lỗi, ${q.job_batches_deleted} lô`,
        );
    }

    if (details.clear_sessions) {
        const s = details.clear_sessions;
        parts.push(
            `Phiên: ${s.db_sessions_deleted} CSDL, ${s.file_sessions_deleted} tệp, ${s.expired_tokens_deleted} mã thông báo`,
        );
    }

    if (details.archive_audit_logs) {
        const a = details.archive_audit_logs;

        if (a.archived_count > 0) {
            parts.push(
                `Nhật ký kiểm toán: Đã lưu trữ ${a.archived_count} dòng sang ${a.disk.toUpperCase()} (${a.archive_file})`,
            );
        } else {
            parts.push('Nhật ký kiểm toán: Không có bản ghi > 6 tháng');
        }
    }

    return parts.join(' | ');
}

const dbHealthGrade = computed(() => {
    let score = 100;

    if (props.stats.failed_jobs_count > 0) {
        score -= 20;
    }

    if (props.stats.expired_sessions_count > 50) {
        score -= 15;
    }

    if (props.stats.old_audit_logs_count > 0) {
        score -= 20;
    }

    if (!props.stats.is_s3_configured) {
        score -= 15;
    }

    if (score >= 90) {
        return {
            grade: 'A+',
            color: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20',
            label: 'Tối ưu',
            desc: 'Cơ sở dữ liệu đang ở trạng thái sạch sẽ và hoạt động với hiệu suất tối đa.',
        };
    }

    if (score >= 70) {
        return {
            grade: 'B',
            color: 'text-amber-500 bg-amber-500/10 border-amber-500/20',
            label: 'Cần chú ý',
            desc: 'Phát hiện một số tài nguyên thừa hoặc cấu hình lưu trữ chưa tối ưu.',
        };
    }

    return {
        grade: 'C',
        color: 'text-rose-500 bg-rose-500/10 border-rose-500/20',
        label: 'Cần tối ưu',
        desc: 'Cơ sở dữ liệu đang có dung lượng rác tích tụ lớn hoặc thiếu cấu hình sao lưu an toàn.',
    };
});

const s3Advice = computed(() => {
    if (!props.stats.is_s3_configured) {
        return {
            status: 'warning',
            text: 'Cấu hình S3 chưa hoạt động. Các bản sao lưu chỉ được lưu cục bộ trên máy chủ này, có nguy cơ mất dữ liệu cao.',
        };
    }

    return {
        status: 'ok',
        text: 'Kho lưu trữ đám mây S3 hoạt động tốt. Bản sao lưu được phân tán an toàn.',
    };
});

const failedJobsAdvice = computed(() => {
    if (props.stats.failed_jobs_count > 0) {
        return {
            status: 'warning',
            text: `Phát hiện ${props.stats.failed_jobs_count} jobs hàng đợi lỗi chưa xóa. Kích hoạt dọn dẹp hàng đợi để giải phóng DB.`,
        };
    }

    return { status: 'ok', text: 'Hàng đợi lỗi sạch sẽ.' };
});

const sessionsAdvice = computed(() => {
    if (props.stats.expired_sessions_count > 100) {
        return {
            status: 'warning',
            text: `Phát hiện ${props.stats.expired_sessions_count} phiên hết hạn. Kích hoạt xóa phiên để tăng tốc truy vấn đăng nhập.`,
        };
    }

    return { status: 'ok', text: 'Không có phiên hết hạn tích tụ.' };
});

const auditLogsAdvice = computed(() => {
    if (props.stats.old_audit_logs_count > 0) {
        return {
            status: 'warning',
            text: `Có ${props.stats.old_audit_logs_count} dòng nhật ký kiểm toán cũ (> 6 tháng). Nên kích hoạt dọn dẹp & nén gửi lên S3.`,
        };
    }

    return {
        status: 'ok',
        text: 'Các nhật ký kiểm toán cũ đã được giải phóng sạch sẽ khỏi CSDL chính.',
    };
});

const oldLogsPercentage = computed(() => {
    if (!props.stats.total_audit_logs) {
        return 0;
    }

    return Math.round(
        (props.stats.old_audit_logs_count / props.stats.total_audit_logs) * 100,
    );
});

const hasHealthWarnings = computed(() => {
    return (
        !props.stats.is_s3_configured ||
        props.stats.failed_jobs_count > 0 ||
        props.stats.expired_sessions_count > 100 ||
        props.stats.old_audit_logs_count > 0
    );
});
</script>

<template>
    <Head title="Sao lưu & Tối ưu DB" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Sao lưu & Tối ưu hóa Cơ sở dữ liệu"
            subtitle="Quản lý sao lưu dữ liệu, dọn dẹp tài nguyên thừa và nén lưu trữ nhật ký kiểm toán cũ."
            :icon="Database"
        >
            <template #actions>
                <LedIndicator
                    v-if="stats.is_s3_configured"
                    status="online"
                    label="Kho đám mây S3 đang hoạt động"
                />
                <LedIndicator v-else status="warning" label="Lưu trữ cục bộ" />
            </template>
        </PageHeader>

        <div class="flex items-center justify-end gap-3">
            <Button
                variant="outline"
                size="sm"
                class="h-9 cursor-pointer gap-1.5 rounded-xl border-border text-xs font-bold hover:bg-muted"
                @click="router.reload({ preserveScroll: true })"
            >
                <RefreshCw class="size-3.5" /> Tải lại dữ liệu
            </Button>
            <Button
                variant="default"
                size="sm"
                class="h-9 cursor-pointer gap-1.5 rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 text-xs font-bold text-white shadow-xs hover:from-orange-600 hover:to-amber-600"
                @click="runBackup"
                :disabled="backingUp"
            >
                <Database
                    class="size-3.5"
                    :class="{ 'animate-spin': backingUp }"
                />
                {{ backingUp ? 'Đang sao lưu...' : 'Kích hoạt sao lưu' }}
            </Button>
        </div>

        <!-- Stats Overview Widgets -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >Cơ sở dữ liệu chính</CardTitle
                    >
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span
                            class="font-mono text-2xl font-bold text-slate-800 dark:text-slate-200"
                            >{{ stats.db_size_mb }}</span
                        >
                        <span
                            class="text-xs font-semibold text-muted-foreground"
                            >MB ({{ stats.db_name }})</span
                        >
                    </div>
                    <Database
                        class="pointer-events-none absolute right-4 bottom-4 size-10 text-orange-500/10"
                    />
                </CardContent>
            </Card>

            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >Hàng đợi lỗi cần xóa</CardTitle
                    >
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span
                            class="font-mono text-2xl font-bold"
                            :class="
                                stats.failed_jobs_count > 0
                                    ? 'text-amber-500'
                                    : 'text-emerald-500'
                            "
                            >{{ stats.failed_jobs_count }}</span
                        >
                        <span
                            class="text-xs font-semibold text-muted-foreground"
                            >jobs lỗi</span
                        >
                    </div>
                    <Activity
                        class="pointer-events-none absolute right-4 bottom-4 size-10 text-amber-500/10"
                    />
                </CardContent>
            </Card>

            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >Phiên hết hạn</CardTitle
                    >
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span
                            class="font-mono text-2xl font-bold text-slate-800 dark:text-slate-200"
                            >{{ stats.expired_sessions_count }}</span
                        >
                        <span
                            class="text-xs font-semibold text-muted-foreground"
                            >phiên hết hạn</span
                        >
                    </div>
                    <Clock
                        class="pointer-events-none absolute right-4 bottom-4 size-10 text-slate-500/10"
                    />
                </CardContent>
            </Card>

            <Card
                class="relative overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
            >
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >Nhật ký kiểm toán cũ (>6 tháng)</CardTitle
                    >
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span
                            class="font-mono text-2xl font-bold"
                            :class="
                                stats.old_audit_logs_count > 0
                                    ? 'dark:text-rose-450 text-rose-500'
                                    : 'text-emerald-500'
                            "
                        >
                            {{ stats.old_audit_logs_count }}
                        </span>
                        <span
                            class="text-xs font-semibold text-muted-foreground"
                            >/ {{ stats.total_audit_logs }} bản ghi</span
                        >
                    </div>
                    <FileArchive
                        class="pointer-events-none absolute right-4 bottom-4 size-10 text-rose-500/10"
                    />
                </CardContent>
            </Card>
        </div>

        <!-- Warning local storage alert -->
        <div
            v-if="!stats.is_s3_configured"
            class="flex items-start gap-3 rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-4 text-amber-800 backdrop-blur-md dark:text-amber-300"
        >
            <AlertCircle class="mt-0.5 size-5 shrink-0 text-amber-500" />
            <div>
                <h4 class="text-xs font-bold tracking-wider uppercase">
                    Cảnh báo: Kho lưu trữ đám mây S3 chưa cấu hình
                </h4>
                <p class="mt-1 text-xs leading-relaxed font-medium">
                    Hệ thống sẽ lưu trữ tạm thời các tệp sao lưu `.sql.gz` và
                    tệp lưu trữ `.json.gz` của nhật ký kiểm toán vào ổ đĩa máy
                    chủ cục bộ (`storage/app/private`). Để đảm bảo an toàn thảm
                    họa, vui lòng thiết lập các tham số `AWS_ACCESS_KEY_ID`,
                    `AWS_SECRET_ACCESS_KEY`, và `AWS_BUCKET` trong cấu hình môi
                    trường (.env).
                </p>
            </div>
        </div>

        <!-- Main Workspace Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Left Side: Backup List & Run Optimization (8 columns) -->
            <div class="flex flex-col gap-6 lg:col-span-8">
                <!-- Backup Manager Card -->
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader
                        class="border-b border-border/40 bg-muted/10 p-5"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                        >
                            <FileArchive class="size-5 text-orange-500" />
                            Quản lý các bản sao lưu CSDL
                        </CardTitle>
                        <CardDescription
                            class="mt-1 text-xs font-semibold text-muted-foreground"
                        >
                            Tải về hoặc xóa các bản sao lưu tự động định kỳ dạng
                            `.sql.gz`. Bộ nhớ mặc định:
                            <span class="font-bold text-orange-500">{{
                                stats.default_disk.toUpperCase()
                            }}</span
                            >.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div
                            v-if="backups.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-muted-foreground/60"
                        >
                            <div class="mb-3 rounded-full bg-muted p-4">
                                <Database class="h-8 w-8 text-slate-400" />
                            </div>
                            <h3
                                class="text-xs font-bold tracking-wider text-foreground uppercase"
                            >
                                Chưa có bản sao lưu nào
                            </h3>
                            <p
                                class="mt-1 max-w-xs text-center text-[10px] font-semibold text-muted-foreground"
                            >
                                Nhấn nút "Kích hoạt sao lưu" ở góc trên bên phải
                                để tạo bản sao lưu đầu tiên.
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
                                        <th class="px-5 py-3.5">
                                            Tên tệp tin sao lưu
                                        </th>
                                        <th class="px-5 py-3.5">Lưu trữ</th>
                                        <th class="px-5 py-3.5">Kích thước</th>
                                        <th class="px-5 py-3.5">Ngày tạo</th>
                                        <th class="px-5 py-3.5 text-right">
                                            Hành động
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/25">
                                    <tr
                                        v-for="file in backups"
                                        :key="file.filename"
                                        class="group transition-colors hover:bg-muted/15"
                                    >
                                        <td
                                            class="dark:text-slate-350 max-w-xs truncate px-5 py-3 font-semibold text-slate-700"
                                            :title="file.filename"
                                        >
                                            {{ file.filename }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <span
                                                class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[9px] font-bold uppercase"
                                                :class="
                                                    file.disk === 's3'
                                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                        : 'border-orange-500/20 bg-orange-500/10 text-orange-600'
                                                "
                                            >
                                                {{ file.disk }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-5 py-3 font-mono text-[10px] font-bold"
                                        >
                                            {{ file.size_mb }} MB
                                        </td>
                                        <td
                                            class="px-5 py-3 text-[10px] font-semibold text-muted-foreground"
                                        >
                                            {{ file.last_modified }}
                                        </td>
                                        <td
                                            class="flex items-center justify-end gap-1.5 px-5 py-3 text-right"
                                        >
                                            <a
                                                :href="`/super-admin/backup-maintenance/download/${file.filename}?disk=${file.disk}`"
                                                class="inline-flex size-8 cursor-pointer items-center justify-center rounded-lg text-orange-500 transition-colors hover:bg-orange-500/10"
                                                title="Tải về bản sao lưu"
                                            >
                                                <Download class="size-4" />
                                            </a>
                                            <button
                                                @click="deleteBackup(file)"
                                                class="flex size-8 cursor-pointer items-center justify-center rounded-lg text-rose-500 transition-colors hover:bg-rose-500/10"
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
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader
                        class="border-b border-border/40 bg-muted/10 p-5"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                        >
                            <Activity class="size-5 text-orange-500" />
                            Kích hoạt tối ưu hóa định kỳ (bảo trì CSDL)
                        </CardTitle>
                        <CardDescription
                            class="mt-1 text-xs font-semibold text-muted-foreground"
                        >
                            Chủ động dọn dẹp các bản ghi rác để cải thiện tốc độ
                            truy vấn SQL chính.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 p-5">
                        <div class="space-y-3">
                            <!-- Task 1 -->
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-border/30 bg-muted/10 p-3.5 transition-all hover:bg-muted/20"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        selectedOptimizeTasks.includes(
                                            'cleanup_queues',
                                        )
                                    "
                                    @change="toggleTask('cleanup_queues')"
                                    class="mt-0.5 size-4.5 rounded border-border text-orange-500 accent-orange-500 focus:ring-orange-500/20"
                                />
                                <div>
                                    <h4
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Dọn dẹp hàng đợi cũ
                                    </h4>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Xóa tất cả các tác vụ hàng đợi bị lỗi
                                        ({{ stats.failed_jobs_count }} failed
                                        jobs) và lịch sử các lô công việc đã
                                        chạy hoàn tất.
                                    </p>
                                </div>
                            </label>

                            <!-- Task 2 -->
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-border/30 bg-muted/10 p-3.5 transition-all hover:bg-muted/20"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        selectedOptimizeTasks.includes(
                                            'clear_sessions',
                                        )
                                    "
                                    @change="toggleTask('clear_sessions')"
                                    class="mt-0.5 size-4.5 rounded border-border text-orange-500 accent-orange-500 focus:ring-orange-500/20"
                                />
                                <div>
                                    <h4
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Xóa lịch sử đăng nhập và phiên hết hạn
                                    </h4>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Xóa các phiên hết hạn (hiện tại có
                                        {{ stats.expired_sessions_count }}
                                        phiên) trong CSDL / kho lưu trữ và các
                                        mã token cá nhân đã quá hạn.
                                    </p>
                                </div>
                            </label>

                            <!-- Task 3 -->
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-xl border border-border/30 bg-muted/10 p-3.5 transition-all hover:bg-muted/20"
                            >
                                <input
                                    type="checkbox"
                                    :checked="
                                        selectedOptimizeTasks.includes(
                                            'archive_audit_logs',
                                        )
                                    "
                                    @change="toggleTask('archive_audit_logs')"
                                    class="mt-0.5 size-4.5 rounded border-border text-orange-500 accent-orange-500 focus:ring-orange-500/20"
                                />
                                <div>
                                    <h4
                                        class="flex flex-wrap items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Lưu trữ & xóa nhật ký kiểm toán cũ hơn 6
                                        tháng
                                        <span
                                            class="py-0.2 dark:text-rose-450 inline-flex rounded border border-rose-500/10 bg-rose-500/10 px-1.5 text-[9px] font-bold text-rose-600"
                                            >Tối ưu dung lượng</span
                                        >
                                    </h4>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                    >
                                        Di chuyển
                                        {{ stats.old_audit_logs_count }} bản ghi
                                        nhật ký hoạt động cũ hơn 6 tháng (trên
                                        tổng số {{ stats.total_audit_logs }})
                                        đóng gói thành tệp nén `.json.gz` gửi
                                        lên S3/MinIO và xóa khỏi DB chính.
                                    </p>
                                </div>
                            </label>
                        </div>

                        <div class="flex justify-end pt-2">
                            <Button
                                variant="default"
                                class="flex h-10 w-full cursor-pointer items-center gap-1.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-6 text-xs font-bold text-white shadow-xs hover:from-emerald-600 hover:to-teal-600 md:w-auto"
                                @click="runOptimization"
                                :disabled="
                                    optimizing ||
                                    selectedOptimizeTasks.length === 0
                                "
                            >
                                <Play
                                    class="size-4"
                                    :class="{ 'animate-pulse': optimizing }"
                                />
                                {{
                                    optimizing
                                        ? 'Đang chạy tối ưu...'
                                        : 'Bắt đầu tối ưu hóa ngay'
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Side: AI DB Coach & Historical Logs (4 columns) -->
            <div class="flex flex-col gap-6 lg:col-span-4">
                <!-- AI DB Coach Card -->
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
                                Trợ lý AI về sức khỏe CSDL
                            </h4>
                            <span
                                class="animate-pulse rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                :class="
                                    hasHealthWarnings
                                        ? 'border-amber-500/20 bg-amber-500/10 text-amber-600'
                                        : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                "
                            >
                                {{
                                    hasHealthWarnings
                                        ? 'Cần chú ý'
                                        : 'Khỏe mạnh'
                                }}
                            </span>
                        </div>

                        <!-- Score Rank Layout -->
                        <div
                            class="flex items-center gap-4 rounded-2xl border border-border/30 bg-muted/15 p-4"
                        >
                            <div
                                class="flex size-14 shrink-0 items-center justify-center rounded-xl border text-xl font-black shadow-xs"
                                :class="dbHealthGrade.color"
                            >
                                {{ dbHealthGrade.grade }}
                            </div>
                            <div>
                                <h5
                                    class="text-xs font-black text-slate-800 dark:text-slate-200"
                                >
                                    Trạng thái: {{ dbHealthGrade.label }}
                                </h5>
                                <p
                                    class="mt-0.5 text-[10px] leading-normal font-semibold text-muted-foreground"
                                >
                                    {{ dbHealthGrade.desc }}
                                </p>
                            </div>
                        </div>

                        <!-- Audit Logs Age Distribution Chart -->
                        <div class="space-y-2">
                            <div
                                class="flex items-center justify-between text-[10px] font-bold text-muted-foreground"
                            >
                                <span>Phân bổ lưu trữ nhật ký kiểm toán</span>
                                <span
                                    >{{ oldLogsPercentage }}% nhật ký cũ
                                    (>6th)</span
                                >
                            </div>
                            <!-- Progress Bar -->
                            <div
                                class="flex h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"
                            >
                                <div
                                    class="h-full bg-rose-500"
                                    :style="{ width: oldLogsPercentage + '%' }"
                                    title="Logs cũ cần đóng gói"
                                ></div>
                                <div
                                    class="h-full flex-1 bg-emerald-500"
                                    title="Logs hoạt động bình thường"
                                ></div>
                            </div>
                            <div
                                class="flex items-center justify-between text-[9px] font-semibold text-muted-foreground"
                            >
                                <span class="flex items-center gap-1"
                                    ><span
                                        class="block size-2 rounded-full bg-rose-500"
                                    ></span>
                                    Cần đóng gói:
                                    {{ stats.old_audit_logs_count }}</span
                                >
                                <span class="flex items-center gap-1"
                                    ><span
                                        class="block size-2 rounded-full bg-emerald-500"
                                    ></span>
                                    Khớp hoạt động:
                                    {{
                                        stats.total_audit_logs -
                                        stats.old_audit_logs_count
                                    }}</span
                                >
                            </div>
                        </div>

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
                                        s3Advice.status === 'warning'
                                            ? 'text-amber-500'
                                            : 'text-emerald-500'
                                    "
                                >
                                    <AlertCircle
                                        v-if="s3Advice.status === 'warning'"
                                        class="size-3.5"
                                    />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p
                                    class="leading-normal font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    {{ s3Advice.text }}
                                </p>
                            </div>

                            <!-- Advice 2 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div
                                    class="mt-0.5 shrink-0"
                                    :class="
                                        failedJobsAdvice.status === 'warning'
                                            ? 'text-amber-500'
                                            : 'text-emerald-500'
                                    "
                                >
                                    <AlertCircle
                                        v-if="
                                            failedJobsAdvice.status ===
                                            'warning'
                                        "
                                        class="size-3.5"
                                    />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p
                                    class="leading-normal font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    {{ failedJobsAdvice.text }}
                                </p>
                            </div>

                            <!-- Advice 3 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div
                                    class="mt-0.5 shrink-0"
                                    :class="
                                        sessionsAdvice.status === 'warning'
                                            ? 'text-amber-500'
                                            : 'text-emerald-500'
                                    "
                                >
                                    <AlertCircle
                                        v-if="
                                            sessionsAdvice.status === 'warning'
                                        "
                                        class="size-3.5"
                                    />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p
                                    class="leading-normal font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    {{ sessionsAdvice.text }}
                                </p>
                            </div>

                            <!-- Advice 4 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div
                                    class="mt-0.5 shrink-0"
                                    :class="
                                        auditLogsAdvice.status === 'warning'
                                            ? 'text-amber-500'
                                            : 'text-emerald-500'
                                    "
                                >
                                    <AlertCircle
                                        v-if="
                                            auditLogsAdvice.status === 'warning'
                                        "
                                        class="size-3.5"
                                    />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p
                                    class="leading-normal font-semibold text-slate-600 dark:text-slate-400"
                                >
                                    {{ auditLogsAdvice.text }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- DB Maintenance Logs Card -->
                <Card
                    class="flex flex-1 flex-col overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardHeader
                        class="border-b border-border/40 bg-muted/10 p-5"
                    >
                        <CardTitle
                            class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-800 uppercase dark:text-slate-200"
                        >
                            <History class="size-5 text-orange-500" />
                            Lịch sử tối ưu hóa (nhật ký bảo trì)
                        </CardTitle>
                        <CardDescription
                            class="mt-1 text-xs font-semibold text-muted-foreground"
                        >
                            Theo dõi kết quả thực thi các tác vụ dọn dẹp và lưu
                            trữ dữ liệu.
                        </CardDescription>
                    </CardHeader>
                    <CardContent
                        class="flex flex-1 flex-col justify-between p-0"
                    >
                        <div
                            v-if="logs.data.length === 0"
                            class="flex flex-col items-center justify-center py-20 text-muted-foreground/60"
                        >
                            <div class="mb-3 rounded-full bg-muted p-4">
                                <History class="h-8 w-8 text-slate-400" />
                            </div>
                            <h3
                                class="text-xs font-bold tracking-wider text-foreground uppercase"
                            >
                                Chưa có lịch sử tối ưu
                            </h3>
                            <p
                                class="mt-1 max-w-xs text-center text-[10px] font-semibold text-muted-foreground"
                            >
                                Hệ thống chưa chạy bất kỳ tác vụ tối ưu hóa thủ
                                công hoặc tự động nào.
                            </p>
                        </div>

                        <div
                            v-else
                            class="flex flex-1 flex-col justify-between"
                        >
                            <div class="divide-y divide-border/20">
                                <div
                                    v-for="log in logs.data"
                                    :key="log.id"
                                    class="p-4 transition-colors hover:bg-muted/10"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                            >
                                                {{ getActionLabel(log.action) }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[10px] font-semibold text-muted-foreground"
                                            >
                                                Thực hiện:
                                                <span class="text-foreground">{{
                                                    log.operator_name
                                                }}</span>
                                                · {{ log.created_at }}
                                            </p>
                                        </div>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-lg border px-2 py-0.5 text-[9px] font-bold"
                                            :class="
                                                log.status === 'success'
                                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                    : 'border-rose-500/20 bg-rose-500/10 text-rose-600'
                                            "
                                        >
                                            <CheckCircle2
                                                v-if="log.status === 'success'"
                                                class="size-3 shrink-0"
                                            />
                                            <XCircle
                                                v-else
                                                class="size-3 shrink-0"
                                            />
                                            {{
                                                log.status === 'success'
                                                    ? 'Thành công'
                                                    : 'Thất bại'
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-2 rounded-lg border border-border/40 bg-background p-2.5 font-mono text-[10px] leading-relaxed font-semibold break-all text-muted-foreground dark:bg-slate-900"
                                    >
                                        {{ formatDetails(log.details) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div
                                v-if="logs.last_page > 1"
                                class="flex items-center justify-between border-t border-border/20 bg-muted/10 px-4 py-3"
                            >
                                <span
                                    class="text-[10px] font-semibold text-muted-foreground"
                                >
                                    Trang {{ logs.current_page }}/{{
                                        logs.last_page
                                    }}
                                    · Tổng {{ logs.total }} dòng
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="logs.current_page <= 1"
                                        @click="
                                            navigatePage(logs.current_page - 1)
                                        "
                                        class="size-8 cursor-pointer rounded-lg border-border p-0"
                                    >
                                        <ChevronLeft class="size-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            logs.current_page >= logs.last_page
                                        "
                                        @click="
                                            navigatePage(logs.current_page + 1)
                                        "
                                        class="size-8 cursor-pointer rounded-lg border-border p-0"
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
