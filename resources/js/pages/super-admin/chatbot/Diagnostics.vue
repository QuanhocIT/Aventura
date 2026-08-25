<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Brain,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    Clock,
    Cpu,
    FlaskConical,
    HelpCircle,
    Loader2,
    MessageSquareX,
    RefreshCcw,
    Search,
    Shield,
    Sparkles,
    Trash2,
    TrendingUp,
    XCircle,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { PageHeader } from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ─── Props ────────────────────────────────────────────────────────────────────
interface UnansweredQuery {
    id: number;
    query: string;
    best_score: number;
    occurrence_count: number;
    last_seen_at: string;
    is_resolved: boolean;
    source: string;
}

interface Health {
    status: string;
    knowledge_count: number;
    cache_age_seconds: number | null;
}

interface Stats {
    total_unanswered: number;
    total_resolved: number;
    total_occurrences: number;
}

interface TestResult {
    found: boolean;
    confidence: number;
    char_score: number;
    word_score: number;
    bm25_score: number;
    keyword_score: number;
    threshold_used: number;
    matched_question: string | null;
    category: string | null;
    answer: string | null;
    error?: string;
}

const props = defineProps<{
    unansweredQueries: UnansweredQuery[];
    showResolved: boolean;
    health: Health;
    stats: Stats;
}>();

// ─── Active tab ───────────────────────────────────────────────────────────────
const activeTab = ref<'unanswered' | 'retrain' | 'playground'>('unanswered');

// ─── Unanswered Queries ───────────────────────────────────────────────────────
const selectedIds = ref<number[]>([]);
const searchQuery = ref('');

const filteredQueries = computed(() => {
    const q = searchQuery.value.toLowerCase();

    if (!q) {
        return props.unansweredQueries;
    }

    return props.unansweredQueries.filter((item) =>
        item.query.toLowerCase().includes(q),
    );
});

function toggleSelect(id: number) {
    const idx = selectedIds.value.indexOf(id);

    if (idx >= 0) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

function toggleSelectAll() {
    if (selectedIds.value.length === filteredQueries.value.length) {
        selectedIds.value = [];
    } else {
        selectedIds.value = filteredQueries.value.map((q) => q.id);
    }
}

function resolveOne(id: number) {
    router.post(
        `/super-admin/chatbot-diagnostics/${id}/resolve`,
        {},
        { preserveScroll: true },
    );
}

function unresolveOne(id: number) {
    router.post(
        `/super-admin/chatbot-diagnostics/${id}/unresolve`,
        {},
        { preserveScroll: true },
    );
}

function bulkResolve() {
    if (!selectedIds.value.length) {
        return;
    }

    router.post(
        '/super-admin/chatbot-diagnostics/bulk-resolve',
        { ids: selectedIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
        },
    );
}

async function deleteResolved() {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Xóa tất cả câu hỏi đã đánh dấu xử lý?',
        }))
    ) {
        return;
    }

    router.delete('/super-admin/chatbot-diagnostics/delete-resolved', {
        preserveScroll: true,
    });
}

function toggleShowResolved() {
    router.get(
        '/super-admin/chatbot-diagnostics',
        {
            resolved: !props.showResolved ? '1' : undefined,
        },
        { preserveState: true, replace: true },
    );
}

function scoreColor(score: number) {
    if (score >= 0.22) {
        return 'text-amber-500';
    }

    if (score >= 0.14) {
        return 'text-orange-500';
    }

    return 'text-red-500';
}

function relativeTime(dateStr: string) {
    const date = new Date(dateStr);
    const now = Date.now();
    const diff = Math.floor((now - date.getTime()) / 1000);

    if (diff < 60) {
        return `${diff}s trước`;
    }

    if (diff < 3600) {
        return `${Math.floor(diff / 60)}m trước`;
    }

    if (diff < 86400) {
        return `${Math.floor(diff / 3600)}h trước`;
    }

    return `${Math.floor(diff / 86400)}d trước`;
}

// ─── Retrain ──────────────────────────────────────────────────────────────────
const retraining = ref(false);

function retrain() {
    retraining.value = true;
    router.post(
        '/super-admin/chatbot-diagnostics/retrain',
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                retraining.value = false;
            },
        },
    );
}

// ─── Playground ───────────────────────────────────────────────────────────────
const playgroundQuery = ref('');
const playgroundLoading = ref(false);
const playgroundResult = ref<TestResult | null>(null);

async function runTestQuery() {
    if (!playgroundQuery.value.trim()) {
        return;
    }

    playgroundLoading.value = true;
    playgroundResult.value = null;

    try {
        const csrf = (
            document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement
        )?.content;
        const res = await fetch('/super-admin/chatbot-diagnostics/test-query', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf ?? '',
                Accept: 'application/json',
            },
            body: JSON.stringify({ query: playgroundQuery.value.trim() }),
        });
        playgroundResult.value = await res.json();
    } catch {
        playgroundResult.value = {
            error: 'Không thể kết nối server.',
            found: false,
            confidence: 0,
            char_score: 0,
            word_score: 0,
            bm25_score: 0,
            keyword_score: 0,
            threshold_used: 0,
            matched_question: null,
            category: null,
            answer: null,
        };
    } finally {
        playgroundLoading.value = false;
    }
}

function scoreBar(score: number) {
    return Math.round(score * 100);
}

function scoreBarColor(score: number) {
    if (score >= 0.6) {
        return 'bg-emerald-500';
    }

    if (score >= 0.35) {
        return 'bg-amber-500';
    }

    if (score >= 0.2) {
        return 'bg-orange-400';
    }

    return 'bg-red-500';
}

// ─── Health status ────────────────────────────────────────────────────────────
const healthStatusClass = computed(() => {
    switch (props.health.status) {
        case 'ok':
            return 'text-emerald-600 bg-emerald-50 border-emerald-200';
        case 'error':
            return 'text-red-600 bg-red-50 border-red-200';
        default:
            return 'text-slate-500 bg-slate-50 border-slate-200';
    }
});

const cacheAge = computed(() => {
    const s = props.health.cache_age_seconds;

    if (s === null) {
        return '—';
    }

    if (s < 60) {
        return `${Math.round(s)}s`;
    }

    return `${Math.floor(s / 60)}m ${s % 60}s`;
});
</script>

<template>
    <Head title="Chẩn đoán Chatbot" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Chẩn đoán & tái huấn luyện Chatbot"
            subtitle="Giám sát hiệu năng · Tái huấn luyện · Kiểm thử câu hỏi"
            :icon="Brain"
            back-href="/super-admin/dashboard"
        />

        <!-- ── Service Health Strip ───────────────────────────────────────── -->
        <div
            class="flex flex-wrap items-center gap-3 rounded-xl border px-4 py-3"
            :class="healthStatusClass"
        >
            <div class="flex items-center gap-2">
                <div class="relative flex size-2.5">
                    <span
                        v-if="health.status === 'ok'"
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex size-2.5 rounded-full"
                        :class="
                            health.status === 'ok'
                                ? 'bg-emerald-500'
                                : 'bg-red-500'
                        "
                    ></span>
                </div>
                <span class="text-sm font-semibold">
                    Dịch vụ Python (cổng 8002):
                    {{
                        health.status === 'ok'
                            ? 'Trực tuyến'
                            : health.status === 'error'
                              ? 'Lỗi kết nối'
                              : 'Không khả dụng'
                    }}
                </span>
            </div>
            <div
                class="ml-auto flex flex-wrap items-center gap-4 text-xs opacity-80"
            >
                <span class="flex items-center gap-1">
                    <ClipboardList class="size-3.5" />
                    {{ health.knowledge_count }} câu hỏi & trả lời trong bộ nhớ
                    đệm
                </span>
                <span class="flex items-center gap-1">
                    <Clock class="size-3.5" />
                    Tuổi bộ nhớ đệm: {{ cacheAge }}
                </span>
            </div>
        </div>

        <!-- ── Stats Summary ──────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
            <Card
                class="border-orange-200 bg-orange-50/50 dark:bg-orange-950/20"
            >
                <CardContent class="pt-4">
                    <div class="flex items-start gap-2">
                        <MessageSquareX class="mt-0.5 size-4 text-orange-500" />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Chưa xử lý
                            </p>
                            <p class="text-2xl font-bold text-orange-600">
                                {{ stats.total_unanswered.toLocaleString() }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ stats.total_occurrences.toLocaleString() }}
                                lượt hỏi
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card
                class="border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20"
            >
                <CardContent class="pt-4">
                    <div class="flex items-start gap-2">
                        <CheckCircle2 class="mt-0.5 size-4 text-emerald-500" />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Đã xử lý
                            </p>
                            <p class="text-2xl font-bold text-emerald-600">
                                {{ stats.total_resolved.toLocaleString() }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <Card
                class="border-violet-200 bg-violet-50/50 dark:bg-violet-950/20"
            >
                <CardContent class="pt-4">
                    <div class="flex items-start gap-2">
                        <TrendingUp class="mt-0.5 size-4 text-violet-500" />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Cơ sở tri thức
                            </p>
                            <p class="text-2xl font-bold text-violet-600">
                                {{ health.knowledge_count }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Hỏi & đáp đang hoạt động
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── Tab navigation ─────────────────────────────────────────────── -->
        <div class="flex w-fit gap-1 rounded-xl bg-muted p-1">
            <button
                @click="activeTab = 'unanswered'"
                :class="[
                    'flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-sm font-medium transition-all',
                    activeTab === 'unanswered'
                        ? 'bg-background text-foreground shadow'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
            >
                <AlertTriangle class="size-4" />
                Nhật ký chưa trả lời
                <span
                    v-if="stats.total_unanswered > 0"
                    class="ml-1 flex size-5 items-center justify-center rounded-full bg-orange-500 text-[10px] font-bold text-white"
                >
                    {{
                        stats.total_unanswered > 99
                            ? '99+'
                            : stats.total_unanswered
                    }}
                </span>
            </button>
            <button
                @click="activeTab = 'retrain'"
                :class="[
                    'flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-sm font-medium transition-all',
                    activeTab === 'retrain'
                        ? 'bg-background text-foreground shadow'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
            >
                <Sparkles class="size-4" />
                Tái huấn luyện mô hình
            </button>
            <button
                @click="activeTab = 'playground'"
                :class="[
                    'flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-sm font-medium transition-all',
                    activeTab === 'playground'
                        ? 'bg-background text-foreground shadow'
                        : 'text-muted-foreground hover:text-foreground',
                ]"
            >
                <FlaskConical class="size-4" />
                Khu vực thử nghiệm câu lệnh
            </button>
        </div>

        <!-- ═══════════════════ TAB: Unanswered Log ═══════════════════════ -->
        <div v-if="activeTab === 'unanswered'" class="flex flex-col gap-4">
            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative max-w-xs flex-1">
                    <Search
                        class="absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="searchQuery"
                        placeholder="Tìm câu hỏi..."
                        class="h-8 w-full rounded-md border border-input bg-background pr-3 pl-8 text-sm shadow-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    />
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs"
                    @click="toggleShowResolved"
                >
                    <CheckCircle2 class="mr-1.5 size-3.5" />
                    {{ showResolved ? 'Xem chưa xử lý' : 'Xem đã xử lý' }}
                </Button>
                <Button
                    v-if="selectedIds.length > 0"
                    size="sm"
                    class="h-8 bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                    @click="bulkResolve"
                >
                    <CheckCircle2 class="mr-1.5 size-3.5" />
                    Đánh dấu đã xử lý ({{ selectedIds.length }})
                </Button>
                <Button
                    v-if="showResolved && stats.total_resolved > 0"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs text-red-500 hover:text-red-600"
                    @click="deleteResolved"
                >
                    <Trash2 class="mr-1.5 size-3.5" />
                    Xóa đã xử lý ({{ stats.total_resolved }})
                </Button>
            </div>

            <!-- Table -->
            <Card>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b bg-muted/40">
                                    <th class="w-8 px-3 py-2.5 text-left">
                                        <input
                                            type="checkbox"
                                            class="size-3.5 rounded"
                                            :checked="
                                                selectedIds.length ===
                                                    filteredQueries.length &&
                                                filteredQueries.length > 0
                                            "
                                            :indeterminate="
                                                selectedIds.length > 0 &&
                                                selectedIds.length <
                                                    filteredQueries.length
                                            "
                                            @change="toggleSelectAll"
                                        />
                                    </th>
                                    <th
                                        class="px-4 py-2.5 text-left font-medium text-muted-foreground"
                                    >
                                        Câu hỏi người dùng
                                    </th>
                                    <th
                                        class="w-24 px-4 py-2.5 text-center font-medium text-muted-foreground"
                                    >
                                        Lượt hỏi
                                    </th>
                                    <th
                                        class="w-28 px-4 py-2.5 text-center font-medium text-muted-foreground"
                                    >
                                        Điểm cao nhất
                                    </th>
                                    <th
                                        class="w-24 px-4 py-2.5 text-center font-medium text-muted-foreground"
                                    >
                                        Nguồn
                                    </th>
                                    <th
                                        class="w-28 px-4 py-2.5 text-center font-medium text-muted-foreground"
                                    >
                                        Lần cuối
                                    </th>
                                    <th
                                        class="w-28 px-4 py-2.5 text-right font-medium text-muted-foreground"
                                    >
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in filteredQueries"
                                    :key="item.id"
                                    class="border-b transition hover:bg-muted/30"
                                    :class="{
                                        'bg-muted/10 opacity-60':
                                            item.is_resolved,
                                    }"
                                >
                                    <td class="px-3 py-2.5">
                                        <input
                                            type="checkbox"
                                            class="size-3.5 rounded"
                                            :checked="
                                                selectedIds.includes(item.id)
                                            "
                                            @change="toggleSelect(item.id)"
                                        />
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-start gap-2">
                                            <HelpCircle
                                                class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                            />
                                            <span
                                                class="line-clamp-2 font-medium"
                                                >{{ item.query }}</span
                                            >
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :class="
                                                item.occurrence_count >= 5
                                                    ? 'bg-orange-100 text-orange-700'
                                                    : 'bg-slate-100 text-slate-600'
                                            "
                                        >
                                            {{ item.occurrence_count }}×
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span
                                            class="font-mono text-xs"
                                            :class="scoreColor(item.best_score)"
                                        >
                                            {{
                                                (item.best_score * 100).toFixed(
                                                    1,
                                                )
                                            }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <Badge
                                            class="border-0 bg-slate-100 text-xs text-slate-600"
                                            >{{ item.source }}</Badge
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-2.5 text-center text-xs text-muted-foreground"
                                    >
                                        {{ relativeTime(item.last_seen_at) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <div
                                            class="flex items-center justify-end gap-1"
                                        >
                                            <button
                                                v-if="!item.is_resolved"
                                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-emerald-600 transition hover:bg-emerald-50"
                                                @click="resolveOne(item.id)"
                                                title="Đánh dấu đã xử lý"
                                            >
                                                <CheckCircle2
                                                    class="size-3.5"
                                                />
                                                Xử lý
                                            </button>
                                            <button
                                                v-else
                                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-slate-500 transition hover:bg-slate-50"
                                                @click="unresolveOne(item.id)"
                                                title="Đánh dấu chưa xử lý"
                                            >
                                                <XCircle class="size-3.5" />
                                                Hoàn tác
                                            </button>
                                            <!-- Quick test in playground -->
                                            <button
                                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-violet-600 transition hover:bg-violet-50"
                                                @click="
                                                    () => {
                                                        playgroundQuery =
                                                            item.query;
                                                        activeTab =
                                                            'playground';
                                                    }
                                                "
                                                title="Thử nghiệm trong Playground"
                                            >
                                                <FlaskConical
                                                    class="size-3.5"
                                                />
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="filteredQueries.length === 0">
                                    <td
                                        colspan="7"
                                        class="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        <CheckCircle2
                                            class="mx-auto mb-2 size-10 text-emerald-400 opacity-60"
                                        />
                                        <p class="font-medium">
                                            {{
                                                showResolved
                                                    ? 'Không có câu hỏi đã xử lý'
                                                    : 'Tuyệt vời! Không có câu hỏi chưa trả lời'
                                            }}
                                        </p>
                                        <p class="mt-1 text-xs">
                                            {{
                                                showResolved
                                                    ? ''
                                                    : 'Chatbot đang hoạt động tốt hoặc chưa có dữ liệu.'
                                            }}
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ═══════════════════ TAB: Retrain Model ════════════════════════ -->
        <div v-if="activeTab === 'retrain'" class="grid gap-6 lg:grid-cols-2">
            <!-- Retrain Card -->
            <Card
                class="border-violet-200 bg-gradient-to-br from-violet-50 to-purple-50 dark:from-violet-950/30 dark:to-purple-950/30"
            >
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Sparkles class="size-5 text-violet-600" />
                        Tái huấn luyện mô hình TF-IDF
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-col gap-5">
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        Sau khi cập nhật cơ sở tri thức (thêm/sửa câu hỏi & trả
                        lời), nhấn nút bên dưới để gửi lệnh đến dịch vụ Python
                        tại
                        <code class="rounded bg-muted px-1 py-0.5 text-xs"
                            >:8002</code
                        >, xóa cache cũ và rebuild toàn bộ mô hình TF-IDF + BM25
                        ngay lập tức.
                    </p>

                    <div
                        class="rounded-xl border border-violet-200 bg-white/70 p-4 dark:bg-violet-950/20"
                    >
                        <p
                            class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Trạng thái hiện tại
                        </p>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Dịch vụ
                                </p>
                                <p
                                    class="font-semibold"
                                    :class="
                                        health.status === 'ok'
                                            ? 'text-emerald-600'
                                            : 'text-red-500'
                                    "
                                >
                                    {{
                                        health.status === 'ok'
                                            ? '✅ Trực tuyến'
                                            : '❌ Ngoại tuyến'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Câu hỏi & trả lời trong bộ nhớ đệm
                                </p>
                                <p class="font-semibold">
                                    {{ health.knowledge_count }} mục
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Tuổi bộ nhớ đệm
                                </p>
                                <p class="font-semibold">{{ cacheAge }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Đường dẫn API
                                </p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    POST /reload-cache
                                </p>
                            </div>
                        </div>
                    </div>

                    <Button
                        class="w-full bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-500/30 transition-all hover:from-violet-700 hover:to-purple-800"
                        :disabled="retraining || health.status !== 'ok'"
                        @click="retrain"
                    >
                        <Loader2
                            v-if="retraining"
                            class="mr-2 size-4 animate-spin"
                        />
                        <RefreshCcw v-else class="mr-2 size-4" />
                        {{
                            retraining
                                ? 'Đang tải lại mô hình...'
                                : 'Tái huấn luyện ngay'
                        }}
                    </Button>

                    <p
                        v-if="health.status !== 'ok'"
                        class="text-center text-xs text-red-500"
                    >
                        Dịch vụ Python đang ngoại tuyến. Kiểm tra trình giám sát
                        dịch vụ.
                    </p>
                </CardContent>
            </Card>

            <!-- How it works -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Cpu class="size-5 text-slate-500" />
                        Cách hoạt động
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="flex flex-col gap-4">
                        <li
                            v-for="(step, i) in [
                                {
                                    icon: ClipboardList,
                                    title: 'Cập nhật cơ sở tri thức',
                                    desc: 'Vào trang Cơ sở tri thức Chatbot, thêm/sửa/xóa câu hỏi & trả lời, từ khóa và câu hỏi tương đương.',
                                },
                                {
                                    icon: Zap,
                                    title: 'Nhấn Tái huấn luyện',
                                    desc: 'Lệnh POST /reload-cache được gửi đến dịch vụ Python. Mô hình xóa bộ nhớ đệm cũ và bắt đầu xây dựng lại.',
                                },
                                {
                                    icon: Brain,
                                    title: 'Xây dựng lại TF-IDF + BM25',
                                    desc: 'Scikit-learn đọc lại toàn bộ dữ liệu từ DB, huấn luyện lại vectorizer Char n-gram (1-3), Word n-gram (1-2) và BM25Okapi.',
                                },
                                {
                                    icon: Shield,
                                    title: 'Kiểm tra với khu vực thử nghiệm',
                                    desc: 'Dùng tab khu vực thử nghiệm câu lệnh để kiểm tra độ tương đồng của các câu hỏi thực tế trước khi đưa vào sản xuất.',
                                },
                            ]"
                            :key="i"
                            class="flex gap-3"
                        >
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-bold text-violet-700"
                            >
                                {{ i + 1 }}
                            </div>
                            <div>
                                <p class="text-sm font-medium">
                                    {{ step.title }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ step.desc }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </div>

        <!-- ═══════════════════ TAB: Prompt Playground ════════════════════ -->
        <div
            v-if="activeTab === 'playground'"
            class="grid gap-6 lg:grid-cols-2"
        >
            <!-- Input -->
            <div class="flex flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <FlaskConical class="size-5 text-amber-500" />
                            Kiểm thử câu hỏi
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <p class="text-sm text-muted-foreground">
                            Nhập câu hỏi bất kỳ để xem điểm tương đồng từng tín
                            hiệu (Char TF-IDF, Word TF-IDF, BM25, từ khóa) mà mô
                            hình trả về.
                        </p>
                        <textarea
                            v-model="playgroundQuery"
                            placeholder="Nhập câu hỏi để kiểm thử... vd: Làm sao đăng ký tài khoản?"
                            rows="4"
                            class="flex w-full rounded-xl border border-input bg-muted/30 px-3 py-2 text-sm transition placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @keydown.ctrl.enter="runTestQuery"
                        />
                        <Button
                            class="w-full bg-gradient-to-r from-amber-500 to-orange-600 text-white shadow-lg shadow-amber-500/30 hover:from-amber-600 hover:to-orange-700"
                            :disabled="
                                playgroundLoading || !playgroundQuery.trim()
                            "
                            @click="runTestQuery"
                        >
                            <Loader2
                                v-if="playgroundLoading"
                                class="mr-2 size-4 animate-spin"
                            />
                            <Activity v-else class="mr-2 size-4" />
                            {{
                                playgroundLoading
                                    ? 'Đang phân tích...'
                                    : 'Phân tích ngay (Ctrl+Enter)'
                            }}
                        </Button>

                        <!-- Quick test from unanswered -->
                        <div
                            v-if="unansweredQueries.length > 0"
                            class="flex flex-col gap-2"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                Thử nhanh từ nhật ký chưa trả lời:
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="item in unansweredQueries.slice(
                                        0,
                                        5,
                                    )"
                                    :key="item.id"
                                    class="line-clamp-1 max-w-[200px] rounded-lg border bg-muted/50 px-2.5 py-1 text-left text-xs transition hover:bg-muted"
                                    @click="
                                        () => {
                                            playgroundQuery = item.query;
                                            runTestQuery();
                                        }
                                    "
                                >
                                    {{ item.query }}
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Result -->
            <div class="flex flex-col gap-4">
                <!-- No result -->
                <Card
                    v-if="!playgroundResult && !playgroundLoading"
                    class="border-dashed"
                >
                    <CardContent
                        class="flex flex-col items-center justify-center py-12 text-center"
                    >
                        <FlaskConical
                            class="mb-3 size-10 text-muted-foreground/40"
                        />
                        <p class="text-sm text-muted-foreground">
                            Kết quả phân tích sẽ hiển thị ở đây.
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Nhập câu hỏi và nhấn phân tích.
                        </p>
                    </CardContent>
                </Card>

                <!-- Loading skeleton -->
                <Card v-if="playgroundLoading">
                    <CardContent class="py-6">
                        <div class="flex animate-pulse flex-col gap-3">
                            <div class="h-6 w-2/3 rounded-lg bg-muted"></div>
                            <div class="h-4 w-1/2 rounded-lg bg-muted"></div>
                            <div class="mt-2 flex flex-col gap-2">
                                <div class="h-4 rounded-lg bg-muted"></div>
                                <div class="h-4 rounded-lg bg-muted"></div>
                                <div class="h-4 rounded-lg bg-muted"></div>
                                <div class="h-4 rounded-lg bg-muted"></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Error -->
                <Card
                    v-if="playgroundResult?.error"
                    class="border-red-200 bg-red-50/50"
                >
                    <CardContent class="flex items-start gap-2 pt-5">
                        <XCircle class="mt-0.5 size-5 shrink-0 text-red-500" />
                        <div>
                            <p class="font-medium text-red-700">Lỗi kết nối</p>
                            <p class="mt-1 text-sm text-red-600">
                                {{ playgroundResult.error }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Success result -->
                <template v-if="playgroundResult && !playgroundResult.error">
                    <!-- Verdict -->
                    <Card
                        :class="
                            playgroundResult.found
                                ? 'border-emerald-200 bg-emerald-50/50'
                                : 'border-red-200 bg-red-50/50'
                        "
                    >
                        <CardContent class="pt-5">
                            <div class="mb-3 flex items-center gap-2">
                                <CheckCircle2
                                    v-if="playgroundResult.found"
                                    class="size-6 text-emerald-600"
                                />
                                <XCircle v-else class="size-6 text-red-500" />
                                <div>
                                    <p
                                        class="text-base font-bold"
                                        :class="
                                            playgroundResult.found
                                                ? 'text-emerald-700'
                                                : 'text-red-700'
                                        "
                                    >
                                        {{
                                            playgroundResult.found
                                                ? '✅ Câu hỏi được nhận diện'
                                                : '❌ Dưới ngưỡng tương đồng'
                                        }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        Độ tin cậy:
                                        <span class="font-mono font-bold"
                                            >{{
                                                (
                                                    playgroundResult.confidence *
                                                    100
                                                ).toFixed(2)
                                            }}%</span
                                        >
                                        &nbsp;·&nbsp; Ngưỡng:
                                        <span class="font-mono"
                                            >{{
                                                (
                                                    playgroundResult.threshold_used *
                                                    100
                                                ).toFixed(1)
                                            }}%</span
                                        >
                                    </p>
                                </div>
                            </div>
                            <div
                                v-if="playgroundResult.matched_question"
                                class="flex items-start gap-2 rounded-lg bg-white/70 px-3 py-2 dark:bg-black/20"
                            >
                                <ChevronRight
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div class="flex flex-col">
                                    <p class="text-xs text-muted-foreground">
                                        Câu hỏi khớp nhất:
                                    </p>
                                    <p class="text-sm font-medium">
                                        {{ playgroundResult.matched_question }}
                                    </p>
                                    <Badge
                                        v-if="playgroundResult.category"
                                        class="mt-1 w-fit text-xs"
                                        >{{ playgroundResult.category }}</Badge
                                    >
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Score breakdown -->
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm text-muted-foreground"
                                >Điểm số chi tiết (tín hiệu tổng hợp)</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="flex flex-col gap-3">
                            <div
                                v-for="sig in [
                                    {
                                        label: 'Char TF-IDF (1-3 grams)',
                                        key: 'char_score',
                                        weight: '35%',
                                        desc: 'Bắt pattern âm tiết tiếng Việt',
                                    },
                                    {
                                        label: 'Word TF-IDF (1-2 grams)',
                                        key: 'word_score',
                                        weight: '25%',
                                        desc: 'Cụm từ nguyên nghĩa',
                                    },
                                    {
                                        label: 'BM25 Okapi',
                                        key: 'bm25_score',
                                        weight: '25%',
                                        desc: 'Mức liên quan từ khóa + lỗi gõ nhẹ',
                                    },
                                    {
                                        label: 'Độ trùng lặp từ khóa',
                                        key: 'keyword_score',
                                        weight: '15%',
                                        desc: 'Từ khoá admin đặt thủ công',
                                    },
                                ]"
                                :key="sig.key"
                                class="flex flex-col gap-1"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-medium">{{
                                            sig.label
                                        }}</span>
                                        <span class="text-muted-foreground"
                                            >· {{ sig.weight }}</span
                                        >
                                    </div>
                                    <span class="font-mono font-bold">
                                        {{
                                            (
                                                (playgroundResult[
                                                    sig.key as keyof TestResult
                                                ] as number) * 100
                                            ).toFixed(1)
                                        }}%
                                    </span>
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-700"
                                        :class="
                                            scoreBarColor(
                                                playgroundResult[
                                                    sig.key as keyof TestResult
                                                ] as number,
                                            )
                                        "
                                        :style="{
                                            width:
                                                scoreBar(
                                                    playgroundResult[
                                                        sig.key as keyof TestResult
                                                    ] as number,
                                                ) + '%',
                                        }"
                                    />
                                </div>
                                <p class="text-[10px] text-muted-foreground">
                                    {{ sig.desc }}
                                </p>
                            </div>

                            <!-- Total -->
                            <div
                                class="mt-1 flex flex-col gap-1 rounded-lg border bg-muted/30 p-3"
                            >
                                <div
                                    class="flex items-center justify-between text-sm"
                                >
                                    <span class="font-semibold"
                                        >Điểm tổng hợp</span
                                    >
                                    <span class="font-mono text-base font-bold"
                                        >{{
                                            (
                                                playgroundResult.confidence *
                                                100
                                            ).toFixed(2)
                                        }}%</span
                                    >
                                </div>
                                <div
                                    class="h-3 overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-700"
                                        :class="
                                            playgroundResult.found
                                                ? 'bg-emerald-500'
                                                : 'bg-red-500'
                                        "
                                        :style="{
                                            width:
                                                scoreBar(
                                                    playgroundResult.confidence,
                                                ) + '%',
                                        }"
                                    />
                                </div>
                                <div
                                    class="flex justify-between text-[10px] text-muted-foreground"
                                >
                                    <span>0%</span>
                                    <span class="text-amber-500"
                                        >Ngưỡng:
                                        {{
                                            (
                                                playgroundResult.threshold_used *
                                                100
                                            ).toFixed(1)
                                        }}%</span
                                    >
                                    <span>100%</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Answer preview -->
                    <Card v-if="playgroundResult.answer">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm text-muted-foreground"
                                >Câu trả lời dự kiến</CardTitle
                            >
                        </CardHeader>
                        <CardContent>
                            <p
                                class="text-sm whitespace-pre-wrap text-foreground"
                            >
                                {{ playgroundResult.answer }}
                            </p>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </div>
</template>
