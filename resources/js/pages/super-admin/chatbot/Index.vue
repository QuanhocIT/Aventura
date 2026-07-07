<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Bot, ChevronDown, ChevronUp, ChevronLeft, ChevronRight, Edit2, Eye, FlaskConical, MessageSquare,
    Plus, RefreshCcw, ThumbsUp, Trash2, X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { PageHeader, DataTable, StatusBadge, EmptyState } from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface KnowledgeItem {
    id: number;
    category: string;
    question: string;
    answer: string;
    alt_questions: string[] | null;
    keywords: string[] | null;
    suggested_questions: string[] | null;
    is_active: boolean;
    view_count: number;
    helpful_count: number;
    unhelpful_count: number;
    display_order: number;
}

type FormData = {
    category: string;
    question: string;
    answer: string;
    alt_questions: string[];
    keywords: string[];
    suggested_questions: string[];
    is_active: boolean;
    display_order: number;
};

const props = defineProps<{
    items: KnowledgeItem[];
    categories: string[];
    filters: { category?: string; search?: string };
    stats: { total: number; active: number; total_views: number; total_helpful: number };
}>();

// ── Filter state ──────────────────────────────────────────────────────────────
const searchQuery = ref(props.filters.search ?? '');
const categoryFilter = ref(props.filters.category ?? '');

// ── Pagination state ─────────────────────────────────────────────────────────
const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() => Math.ceil(props.items.length / itemsPerPage));

const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;

    return props.items.slice(start, end);
});

const visiblePages = computed(() => {
    const pages: number[] = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - 2);
    const end = Math.min(totalPages.value, start + maxVisible - 1);
    
    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }
    
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});

watch(() => props.items, () => {
    if (currentPage.value > totalPages.value) {
        currentPage.value = Math.max(1, totalPages.value);
    }
});

function applyFilters() {
    currentPage.value = 1;
    router.get('/super-admin/chatbot', {
        search: searchQuery.value || undefined,
        category: categoryFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    currentPage.value = 1;
    searchQuery.value = '';
    categoryFilter.value = '';
    router.get('/super-admin/chatbot', {}, { preserveState: true, replace: true });
}

// ── Dialog state ──────────────────────────────────────────────────────────────
const showDialog = ref(false);
const editingItem = ref<KnowledgeItem | null>(null);
const expandedRows = ref<number[]>([]);

const form = useForm<FormData>({
    category: '',
    question: '',
    answer: '',
    alt_questions: [],
    keywords: [],
    suggested_questions: [],
    is_active: true,
    display_order: 0,
});

const altQuestionsText = ref('');
const keywordsText = ref('');
const suggestedQuestionsText = ref('');

function openCreate() {
    editingItem.value = null;
    form.reset();
    form.is_active = true;
    form.display_order = 0;
    altQuestionsText.value = '';
    keywordsText.value = '';
    suggestedQuestionsText.value = '';
    showDialog.value = true;
}

function openEdit(item: KnowledgeItem) {
    editingItem.value = item;
    form.category = item.category;
    form.question = item.question;
    form.answer = item.answer;
    form.alt_questions = item.alt_questions ?? [];
    form.keywords = item.keywords ?? [];
    form.suggested_questions = item.suggested_questions ?? [];
    form.is_active = item.is_active;
    form.display_order = item.display_order;
    altQuestionsText.value = (item.alt_questions ?? []).join('\n');
    keywordsText.value = (item.keywords ?? []).join(', ');
    suggestedQuestionsText.value = (item.suggested_questions ?? []).join('\n');
    showDialog.value = true;
}

function closeDialog() {
    showDialog.value = false;
    editingItem.value = null;
}

function submitForm() {
    form.alt_questions = altQuestionsText.value.split('\n').map(s => s.trim()).filter(Boolean);
    form.keywords = keywordsText.value.split(',').map(s => s.trim()).filter(Boolean);
    form.suggested_questions = suggestedQuestionsText.value.split('\n').map(s => s.trim()).filter(Boolean);

    if (editingItem.value) {
        form.put(`/super-admin/chatbot/${editingItem.value.id}`, {
            preserveScroll: true,
            onSuccess: closeDialog,
        });
    } else {
        form.post('/super-admin/chatbot', {
            preserveScroll: true,
            onSuccess: closeDialog,
        });
    }
}

async function deleteItem(item: KnowledgeItem) {
    if (!(await confirmDialog({ title: 'Xác nhận thao tác', description: `Xóa câu hỏi: "${item.question}"?` }))) {
return;
}

    router.delete(`/super-admin/chatbot/${item.id}`, { preserveScroll: true });
}

function reloadCache() {
    router.post('/super-admin/chatbot/reload-cache', {}, { preserveScroll: true });
}

function toggleRow(id: number) {
    const index = expandedRows.value.indexOf(id);

    if (index >= 0) {
        expandedRows.value.splice(index, 1);
    } else {
        expandedRows.value.push(id);
    }
}

// ── Category display ──────────────────────────────────────────────────────────
const categoryLabels: Record<string, string> = {
    'dang-ky': 'Đăng ký',
    'gia-ca': 'Giá cả',
    'tinh-nang': 'Tính năng',
    'van-hanh': 'Vận hành',
    'bao-mat': 'Bảo mật',
    'ky-thuat': 'Kỹ thuật',
    'ho-tro': 'Hỗ trợ',
    'chung': 'Chung',
};

const categoryColors: Record<string, string> = {
    'dang-ky': 'bg-blue-100 text-blue-700',
    'gia-ca': 'bg-green-100 text-green-700',
    'tinh-nang': 'bg-purple-100 text-purple-700',
    'van-hanh': 'bg-orange-100 text-orange-700',
    'bao-mat': 'bg-red-100 text-red-700',
    'ky-thuat': 'bg-cyan-100 text-cyan-700',
    'ho-tro': 'bg-amber-100 text-amber-700',
    'chung': 'bg-slate-100 text-slate-700',
};

function categoryLabel(cat: string) {
    return categoryLabels[cat] ?? cat;
}

function categoryColor(cat: string) {
    return categoryColors[cat] ?? 'bg-slate-100 text-slate-700';
}

const totalUnhelpful = computed(() => {
    return props.items.reduce((sum, item) => sum + (item.unhelpful_count || 0), 0);
});

const helpfulRate = computed(() => {
    const helpful = props.stats.total_helpful;
    const totalVotes = helpful + totalUnhelpful.value;

    if (totalVotes === 0) {
return 0;
}

    return Math.round((helpful / totalVotes) * 100);
});

const qualityGrade = computed(() => {
    const rate = helpfulRate.value;

    if (rate >= 80) {
return 'Xuất sắc';
}

    if (rate >= 55) {
return 'Khá tốt';
}

    return 'Cần cải thiện';
});

const categoryStats = computed(() => {
    const counts: Record<string, number> = {};
    props.categories.forEach(cat => {
        counts[cat] = 0;
    });
    
    const totalItems = props.items.length;
    props.items.forEach(item => {
        if (counts[item.category] !== undefined) {
            counts[item.category]++;
        } else {
            counts[item.category] = 1;
        }
    });

    return props.categories.map(cat => {
        const count = counts[cat] || 0;
        const percent = totalItems > 0 ? Math.round((count / totalItems) * 100) : 0;

        return {
            value: cat,
            label: categoryLabel(cat),
            count,
            percent,
        };
    }).sort((a, b) => b.count - a.count);
});

const hasActiveFilters = computed(() => !!searchQuery.value || !!categoryFilter.value);
</script>

<template>
    <Head title="Chatbot Knowledge Base" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-11 items-center justify-center rounded-xl bg-gradient-to-br from-orange-500/10 to-amber-500/10 border border-orange-500/20">
                    <Bot class="size-5.5 text-orange-500" />
                </div>
                <div>
                    <h1 class="text-base font-black text-slate-800 dark:text-slate-100 tracking-wide">Chatbot Knowledge Base</h1>
                    <p class="text-xs text-muted-foreground font-semibold">Quản lý cơ sở tri thức cho AI chatbot</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Link href="/super-admin/chatbot-diagnostics">
                    <Button variant="outline" size="sm" class="rounded-xl border-border hover:bg-muted font-bold text-xs h-9 cursor-pointer">
                        <FlaskConical class="mr-1.5 size-3.5" />
                        Diagnostics
                    </Button>
                </Link>
                <Button variant="outline" size="sm" @click="reloadCache" class="rounded-xl border-border hover:bg-muted font-bold text-xs h-9 cursor-pointer">
                    <RefreshCcw class="mr-1.5 size-3.5" />
                    Reload Cache
                </Button>
                <Button size="sm" @click="openCreate" class="bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl shadow-xs cursor-pointer font-bold h-9 text-xs transition-colors">
                    <Plus class="mr-1.5 size-4" />
                    Thêm Q&A
                </Button>
            </div>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <!-- Total Q&A -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Tổng Q&A</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-slate-700 dark:text-slate-200">{{ stats.total }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-slate-500/10 flex items-center justify-center border border-slate-500/20 text-slate-500">
                    <MessageSquare class="size-4" />
                </div>
            </div>

            <!-- Active -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Đang hoạt động</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-emerald-500">{{ stats.active }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20 text-emerald-500">
                    <Bot class="size-4" />
                </div>
            </div>

            <!-- Views -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Lượt xem</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-violet-500">{{ stats.total_views.toLocaleString() }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-violet-500/10 flex items-center justify-center border border-violet-500/20 text-violet-500">
                    <Eye class="size-4" />
                </div>
            </div>

            <!-- Helpful -->
            <div class="flex items-center justify-between p-4 bg-card/40 border border-border/40 backdrop-blur-md rounded-2xl shadow-3xs transition-all duration-300 hover:shadow-xs">
                <div>
                    <p class="text-[9px] font-black text-muted-foreground uppercase tracking-wider">Hữu ích</p>
                    <h3 class="text-2xl font-black font-mono tracking-tight mt-1 text-blue-500">{{ stats.total_helpful.toLocaleString() }}</h3>
                </div>
                <div class="size-9 rounded-xl bg-blue-500/10 flex items-center justify-center border border-blue-500/20 text-blue-500">
                    <ThumbsUp class="size-4" />
                </div>
            </div>
        </div>

        <!-- Main Layout Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Left Column: Filters & Table -->
            <div class="lg:col-span-3 space-y-5">
                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <Input
                        v-model="searchQuery"
                        placeholder="Tìm kiếm câu hỏi..."
                        class="h-9 w-full sm:w-64 text-xs rounded-xl border-border bg-background"
                        @keydown.enter="applyFilters"
                    />
                    <Select v-model="categoryFilter" @update:model-value="applyFilters">
                        <SelectTrigger class="h-9 w-40 text-xs rounded-xl border-border bg-background">
                            <SelectValue placeholder="Tất cả danh mục" />
                        </SelectTrigger>
                        <SelectContent class="rounded-xl">
                            <SelectItem value="">Tất cả danh mục</SelectItem>
                            <SelectItem v-for="cat in categories" :key="cat" :value="cat">
                                {{ categoryLabel(cat) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button size="sm" class="h-9 bg-muted/60 hover:bg-muted text-foreground border border-border/40 rounded-xl px-4 font-bold text-xs" @click="applyFilters">Tìm</Button>
                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="h-9 text-rose-500 hover:text-rose-600 hover:bg-rose-500/10 rounded-xl text-xs" @click="clearFilters">
                        <X class="mr-1 size-3.5" /> Xóa bộ lọc
                    </Button>
                </div>

                <!-- Table Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border/40 bg-muted/10">
                                        <th class="px-4 py-3 text-left text-xs font-black text-muted-foreground uppercase tracking-wider w-32">Danh mục</th>
                                        <th class="px-4 py-3 text-left text-xs font-black text-muted-foreground uppercase tracking-wider">Câu hỏi</th>
                                        <th class="px-4 py-3 text-center text-xs font-black text-muted-foreground uppercase tracking-wider w-16">
                                            <Eye class="mx-auto size-4" />
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-black text-muted-foreground uppercase tracking-wider w-16">
                                            <ThumbsUp class="mx-auto size-4" />
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-black text-muted-foreground uppercase tracking-wider w-24">Trạng thái</th>
                                        <th class="px-4 py-3 text-right text-xs font-black text-muted-foreground uppercase tracking-wider w-24">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="item in paginatedItems" :key="item.id">
                                        <!-- Main row -->
                                        <tr
                                            class="border-b border-border/30 transition hover:bg-muted/20 cursor-pointer"
                                            @click="toggleRow(item.id)"
                                        >
                                            <td class="px-4 py-3">
                                                <span
                                                    class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold border"
                                                    :class="categoryColor(item.category)"
                                                >
                                                    {{ categoryLabel(item.category) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-bold text-slate-800 dark:text-slate-200 text-xs">{{ item.question }}</span>
                                                    <component
                                                        :is="expandedRows.includes(item.id) ? ChevronUp : ChevronDown"
                                                        class="size-3.5 shrink-0 text-muted-foreground"
                                                    />
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center text-muted-foreground font-mono font-bold text-xs">{{ item.view_count }}</td>
                                            <td class="px-4 py-3 text-center text-muted-foreground font-mono font-bold text-xs text-blue-500">{{ item.helpful_count }}</td>
                                            <td class="px-4 py-3 text-center">
                                                <span :class="item.is_active ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-slate-500/10 text-slate-500 border-slate-500/20'" class="rounded-full px-2.5 py-0.5 text-[10px] font-bold border">
                                                    {{ item.is_active ? 'Bật' : 'Tắt' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right" @click.stop>
                                                <div class="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" class="size-7 rounded-lg hover:bg-muted/80 text-muted-foreground hover:text-foreground cursor-pointer" @click="openEdit(item)" title="Sửa">
                                                        <Edit2 class="size-3.5" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon" class="size-7 rounded-lg text-rose-500 hover:text-rose-600 hover:bg-rose-500/10 cursor-pointer" @click="deleteItem(item)" title="Xóa">
                                                        <Trash2 class="size-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
 
                                        <!-- Expanded row -->
                                        <tr v-if="expandedRows.includes(item.id)" class="bg-muted/10 border-b border-border/30">
                                            <td colspan="6" class="px-6 py-4">
                                                <div class="grid gap-4 text-xs sm:grid-cols-2">
                                                    <div>
                                                        <p class="mb-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Câu trả lời:</p>
                                                        <p class="whitespace-pre-wrap text-slate-700 dark:text-slate-300 font-semibold leading-relaxed">{{ item.answer }}</p>
                                                    </div>
                                                    <div class="flex flex-col gap-3">
                                                        <div v-if="item.alt_questions?.length">
                                                            <p class="mb-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Câu hỏi tương đương:</p>
                                                            <ul class="list-inside list-disc text-muted-foreground space-y-0.5 font-semibold">
                                                                <li v-for="(q, i) in item.alt_questions" :key="i">{{ q }}</li>
                                                            </ul>
                                                        </div>
                                                        <div v-if="item.keywords?.length">
                                                            <p class="mb-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Từ khoá:</p>
                                                            <div class="flex flex-wrap gap-1">
                                                                <span
                                                                    v-for="(kw, i) in item.keywords"
                                                                    :key="i"
                                                                    class="rounded-lg border border-border bg-background px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:text-slate-400"
                                                                >{{ kw }}</span>
                                                            </div>
                                                        </div>
                                                        <div v-if="item.suggested_questions?.length">
                                                            <p class="mb-1 text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Câu hỏi gợi ý:</p>
                                                            <ul class="list-inside list-disc text-muted-foreground space-y-0.5 font-semibold">
                                                                <li v-for="(q, i) in item.suggested_questions" :key="i">{{ q }}</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
 
                                    <tr v-if="!items.length">
                                        <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                                            <MessageSquare class="mx-auto mb-2 size-8 opacity-30" />
                                            <p class="text-xs font-bold">Chưa có câu hỏi tri thức nào</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="totalPages > 1" class="flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-border/30 px-4 py-3 bg-muted/5">
                            <div class="text-xs text-muted-foreground font-semibold">
                                Hiển thị {{ (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, items.length) }} trong tổng số {{ items.length }} câu hỏi
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-8 rounded-lg cursor-pointer hover:bg-muted"
                                    :disabled="currentPage === 1"
                                    @click="currentPage = 1"
                                >
                                    <span class="sr-only">Trang đầu</span>
                                    <span class="text-xs font-bold">&laquo;</span>
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-8 rounded-lg cursor-pointer hover:bg-muted"
                                    :disabled="currentPage === 1"
                                    @click="currentPage--"
                                >
                                    <ChevronLeft class="size-4" />
                                </Button>
                                
                                <Button
                                    v-for="page in visiblePages"
                                    :key="page"
                                    :variant="currentPage === page ? 'default' : 'outline'"
                                    size="sm"
                                    class="h-8 min-w-8 rounded-lg cursor-pointer font-bold text-xs"
                                    :class="currentPage === page ? 'bg-orange-500 hover:bg-orange-600 text-white border-orange-500 shadow-xs' : 'border-border/60 text-muted-foreground hover:text-foreground hover:bg-muted'"
                                    @click="currentPage = page"
                                >
                                    {{ page }}
                                </Button>

                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-8 rounded-lg cursor-pointer hover:bg-muted"
                                    :disabled="currentPage === totalPages"
                                    @click="currentPage++"
                                >
                                    <ChevronRight class="size-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    class="size-8 rounded-lg cursor-pointer hover:bg-muted"
                                    :disabled="currentPage === totalPages"
                                    @click="currentPage = totalPages"
                                >
                                    <span class="sr-only">Trang cuối</span>
                                    <span class="text-xs font-bold">&raquo;</span>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Column: Analytics & AI Advisor -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Category Share Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardContent class="p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-black text-muted-foreground uppercase tracking-wider">Phân bổ chủ đề tri thức</h4>
                            <span class="p-1 rounded bg-orange-500/10 text-orange-500 text-[10px] font-bold">Tỷ lệ Q&A</span>
                        </div>
                        <div class="space-y-3.5">
                            <div v-for="stat in categoryStats" :key="stat.value" class="space-y-1.5">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-bold text-slate-700 dark:text-slate-300">{{ stat.label }}</span>
                                    <span class="font-mono font-black text-slate-800 dark:text-slate-200">
                                        {{ stat.count }} câu ({{ stat.percent }}%)
                                    </span>
                                </div>
                                <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden relative">
                                    <div 
                                        class="h-full rounded-full transition-all duration-500 ease-out"
                                        :class="
                                            stat.value === 'dang-ky' ? 'bg-gradient-to-r from-blue-500 to-sky-400' :
                                            stat.value === 'gia-ca' ? 'bg-gradient-to-r from-emerald-500 to-teal-400' :
                                            stat.value === 'tinh-nang' ? 'bg-gradient-to-r from-purple-500 to-indigo-400' :
                                            stat.value === 'van-hanh' ? 'bg-gradient-to-r from-orange-500 to-amber-400' :
                                            stat.value === 'bao-mat' ? 'bg-gradient-to-r from-red-500 to-rose-400' :
                                            stat.value === 'ky-thuat' ? 'bg-gradient-to-r from-cyan-500 to-sky-400' :
                                            stat.value === 'ho-tro' ? 'bg-gradient-to-r from-amber-500 to-yellow-400' : 'bg-gradient-to-r from-slate-500 to-slate-400'
                                        "
                                        :style="`width: ${stat.percent}%`"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- AI Quality Advisor Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardContent class="p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-border/40 pb-3">
                            <h4 class="text-xs font-black text-muted-foreground uppercase tracking-wider">Đo lường & AI Advisor</h4>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase border animate-pulse"
                                :class="
                                    qualityGrade === 'Xuất sắc' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' :
                                    qualityGrade === 'Khá tốt' ? 'bg-blue-500/10 text-blue-600 border-blue-500/20' : 'bg-rose-500/10 text-rose-600 border-rose-500/20'
                                "
                            >
                                Tri thức: {{ qualityGrade }}
                            </span>
                        </div>

                        <!-- Quality indicators row -->
                        <div class="grid grid-cols-3 gap-2.5">
                            <div class="p-2.5 rounded-xl border border-border/30 bg-muted/10 text-center">
                                <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-wider block">Hữu ích (Helpful)</span>
                                <span class="text-xs font-black text-slate-800 dark:text-slate-200 mt-1 block font-mono">{{ helpfulRate }}%</span>
                            </div>
                            <div class="p-2.5 rounded-xl border border-border/30 bg-muted/10 text-center">
                                <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-wider block">Phiếu bình chọn</span>
                                <span class="text-xs font-black text-slate-800 dark:text-slate-200 mt-1 block font-mono">{{ stats.total_helpful + totalUnhelpful }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl border border-border/30 bg-muted/10 text-center">
                                <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-wider block">Độ tắt (Inactive)</span>
                                <span class="text-xs font-black text-slate-800 dark:text-slate-200 mt-1 block font-mono">{{ Math.round(((stats.total - stats.active) / stats.total) * 100) || 0 }}%</span>
                            </div>
                        </div>

                        <!-- AI Suggestions -->
                        <div class="space-y-3.5 pt-2">
                            <!-- Suggestion 1: Low helpful rate -->
                            <div v-if="helpfulRate < 75" class="p-3 rounded-xl border border-orange-500/20 bg-orange-500/[0.03] flex items-start gap-2.5">
                                <div class="p-1 bg-orange-500/10 text-orange-500 rounded-lg shrink-0 mt-0.5">
                                    <Percent class="size-4.5" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Nâng cấp chất lượng trả lời</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5">Tỷ lệ hữu ích đang ở mức thấp ({{ helpfulRate }}%). Hãy rà soát lại các câu trả lời ngắn hoặc bổ sung thêm từ khóa để Chatbot khớp lệnh chính xác hơn.</p>
                                </div>
                            </div>

                            <!-- Suggestion 2: Inactive items -->
                            <div v-if="stats.total - stats.active > 0" class="p-3 rounded-xl border border-blue-500/20 bg-blue-500/[0.03] flex items-start gap-2.5">
                                <div class="p-1 bg-blue-500/10 text-blue-500 rounded-lg shrink-0 mt-0.5">
                                    <Clock class="size-4.5" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Câu hỏi chưa kích hoạt</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5">Bạn đang tắt {{ stats.total - stats.active }} câu hỏi tri thức. Hãy rà soát nội dung và kích hoạt chúng để mở rộng khả năng giải đáp tự động.</p>
                                </div>
                            </div>

                            <!-- Suggestion 3: General low total items -->
                            <div v-if="stats.total < 15" class="p-3 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.03] flex items-start gap-2.5">
                                <div class="p-1 bg-emerald-500/10 text-emerald-500 rounded-lg shrink-0 mt-0.5">
                                    <Plus class="size-4.5" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Bổ sung Q&A hệ thống</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5">Số lượng câu hỏi tri thức chatbot còn khiêm tốn ({{ stats.total }} Q&As). Hãy thêm câu hỏi thuộc nhóm hỗ trợ, kỹ thuật hoặc đăng ký để giảm tải ticket.</p>
                                </div>
                            </div>

                            <!-- Suggestion 4: General good performance -->
                            <div v-if="helpfulRate >= 75 && stats.total >= 15" class="p-3 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.03] flex items-start gap-2.5">
                                <div class="p-1 bg-emerald-500/10 text-emerald-500 rounded-lg shrink-0 mt-0.5">
                                    <CheckCircle class="size-4.5" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Tri thức chatbot tối ưu tốt</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5">Hệ thống tri thức hoạt động ổn định với tỷ lệ hữu ích cao. Tiếp tục theo dõi để cập nhật thêm các từ khóa (Keywords) mới phát sinh.</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Dialog: Create / Edit -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="showDialog" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 backdrop-blur-xs p-4 pt-10">
            <div class="flex w-full max-w-2xl flex-col gap-5 rounded-2xl bg-card/90 border border-border/40 p-6 shadow-2xl mb-10 backdrop-blur-md">
                <div class="flex items-center justify-between border-b border-border/40 pb-3">
                    <div class="flex flex-col gap-0.5">
                        <h2 class="text-sm font-black text-slate-800 dark:text-slate-100 tracking-wide">
                            {{ editingItem ? 'Chỉnh sửa Q&A' : 'Thêm câu hỏi mới' }}
                        </h2>
                        <p class="text-[10px] text-muted-foreground font-semibold">Cập nhật cơ sở tri thức giúp Chatbot AI phản hồi thông minh</p>
                    </div>
                    <Button variant="ghost" size="icon" class="size-7 rounded-lg text-muted-foreground hover:text-foreground cursor-pointer" @click="closeDialog">
                        <X class="size-4" />
                    </Button>
                </div>

                <form @submit.prevent="submitForm" class="flex flex-col gap-4">
                    <!-- Category + display_order -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Danh mục <span class="text-rose-500">*</span></Label>
                            <Select v-model="form.category">
                                <SelectTrigger class="rounded-xl border border-border bg-background focus:ring-orange-500/20 focus:border-orange-500 text-xs h-9 font-semibold">
                                    <SelectValue placeholder="Chọn danh mục" />
                                </SelectTrigger>
                                <SelectContent class="rounded-xl">
                                    <SelectItem v-for="cat in categories" :key="cat" :value="cat" class="text-xs font-semibold cursor-pointer">
                                        <div class="flex items-center gap-2">
                                            <span class="size-2 rounded-full" :class="
                                                cat === 'dang-ky' ? 'bg-blue-500' :
                                                cat === 'gia-ca' ? 'bg-green-500' :
                                                cat === 'tinh-nang' ? 'bg-purple-500' :
                                                cat === 'van-hanh' ? 'bg-orange-500' :
                                                cat === 'bao-mat' ? 'bg-red-500' :
                                                cat === 'ky-thuat' ? 'bg-cyan-500' :
                                                cat === 'ho-tro' ? 'bg-amber-500' : 'bg-slate-500'
                                            "></span>
                                            {{ categoryLabel(cat) }}
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.category" class="text-xs text-red-500 font-semibold">{{ form.errors.category }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Thứ tự hiển thị</Label>
                            <Input v-model.number="form.display_order" type="number" min="0" max="9999" class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold font-mono" />
                        </div>
                    </div>

                    <!-- Question -->
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Câu hỏi chính <span class="text-rose-500">*</span></Label>
                        <Input v-model="form.question" placeholder="Làm sao để đăng ký tài khoản?" class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" />
                        <p v-if="form.errors.question" class="text-xs text-red-500 font-semibold">{{ form.errors.question }}</p>
                    </div>

                    <!-- Answer -->
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Câu trả lời <span class="text-rose-500">*</span></Label>
                        <textarea
                            v-model="form.answer"
                            placeholder="Nhập câu trả lời cụ thể cho khách hàng..."
                            rows="4"
                            class="flex min-h-[80px] w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 font-semibold leading-relaxed"
                        />
                        <p v-if="form.errors.answer" class="text-xs text-red-500 font-semibold">{{ form.errors.answer }}</p>
                    </div>

                    <!-- Alt questions -->
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Câu hỏi tương đương <span class="text-[9px] text-muted-foreground font-semibold">(Mỗi dòng một câu)</span></Label>
                        <textarea
                            v-model="altQuestionsText"
                            placeholder="Tôi muốn tạo tài khoản&#10;Đăng ký như thế nào"
                            rows="3"
                            class="flex min-h-[60px] w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 font-semibold leading-relaxed"
                        />
                    </div>

                    <!-- Keywords -->
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Từ khoá liên quan <span class="text-[9px] text-muted-foreground font-semibold">(phân cách các thẻ bằng dấu phẩy)</span></Label>
                        <Input v-model="keywordsText" placeholder="đăng ký, tài khoản, tạo mới" class="rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" />
                    </div>

                    <!-- Suggested questions -->
                    <div class="flex flex-col gap-1.5">
                        <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Câu hỏi gợi ý tiếp theo <span class="text-[9px] text-muted-foreground font-semibold">(Mỗi dòng một câu)</span></Label>
                        <textarea
                            v-model="suggestedQuestionsText"
                            placeholder="Gói dịch vụ có những loại nào?&#10;Chi phí sử dụng là bao nhiêu?"
                            rows="3"
                            class="flex min-h-[60px] w-full rounded-xl border border-border bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 font-semibold leading-relaxed"
                        />
                    </div>

                    <!-- is_active iOS Switch -->
                    <div class="flex items-center justify-between p-3 bg-muted/15 rounded-xl border border-border/20">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Kích hoạt phản hồi</span>
                            <span class="text-[9px] text-muted-foreground font-semibold">Cho phép chatbot tự động sử dụng giải đáp này</span>
                        </div>
                        <button type="button" @click="form.is_active = !form.is_active"
                            :class="form.is_active ? 'bg-orange-500' : 'bg-slate-300 dark:bg-slate-700'"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none">
                            <span :class="form.is_active ? 'translate-x-4' : 'translate-x-0'"
                                class="pointer-events-none inline-block size-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"></span>
                        </button>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-border/40">
                        <Button type="button" variant="outline" @click="closeDialog" class="rounded-xl border-border hover:bg-muted font-bold text-xs h-9 cursor-pointer">Hủy</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl shadow-xs cursor-pointer font-bold h-9 text-xs transition-colors">
                            {{ editingItem ? 'Cập nhật' : 'Thêm mới' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>
</template>
