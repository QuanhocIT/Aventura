<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Bot, ChevronDown, ChevronUp, Edit2, Eye, MessageSquare,
    Plus, RefreshCcw, ThumbsUp, Trash2, X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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

function applyFilters() {
    router.get('/super-admin/chatbot', {
        search: searchQuery.value || undefined,
        category: categoryFilter.value || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
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

function deleteItem(item: KnowledgeItem) {
    if (!confirm(`Xóa câu hỏi: "${item.question}"?`)) {
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

const hasActiveFilters = computed(() => !!searchQuery.value || !!categoryFilter.value);
</script>

<template>
    <Head title="Chatbot Knowledge Base" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10">
                    <Bot class="size-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold">Chatbot Knowledge Base</h1>
                    <p class="text-sm text-muted-foreground">Quản lý cơ sở tri thức cho AI chatbot</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button variant="outline" size="sm" @click="reloadCache">
                    <RefreshCcw class="mr-1.5 size-3.5" />
                    Reload Cache
                </Button>
                <Button size="sm" @click="openCreate">
                    <Plus class="mr-1.5 size-3.5" />
                    Thêm Q&A
                </Button>
            </div>
        </div>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Tổng Q&A</p>
                    <p class="text-2xl font-bold">{{ stats.total }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Đang hoạt động</p>
                    <p class="text-2xl font-bold text-green-600">{{ stats.active }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Lượt xem</p>
                    <p class="text-2xl font-bold">{{ stats.total_views.toLocaleString() }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4">
                    <p class="text-xs text-muted-foreground">Hữu ích</p>
                    <p class="text-2xl font-bold text-blue-600">{{ stats.total_helpful.toLocaleString() }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3">
            <Input
                v-model="searchQuery"
                placeholder="Tìm kiếm câu hỏi..."
                class="h-8 w-64 text-sm"
                @keydown.enter="applyFilters"
            />
            <Select v-model="categoryFilter" @update:model-value="applyFilters">
                <SelectTrigger class="h-8 w-40 text-sm">
                    <SelectValue placeholder="Tất cả danh mục" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả danh mục</SelectItem>
                    <SelectItem v-for="cat in categories" :key="cat" :value="cat">
                        {{ categoryLabel(cat) }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button variant="ghost" size="sm" @click="applyFilters" class="h-8">Tìm</Button>
            <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="clearFilters" class="h-8 text-muted-foreground">
                <X class="mr-1 size-3.5" /> Xóa bộ lọc
            </Button>
        </div>

        <!-- Table -->
        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th class="px-4 py-2.5 text-left font-medium text-muted-foreground">Danh mục</th>
                                <th class="px-4 py-2.5 text-left font-medium text-muted-foreground">Câu hỏi</th>
                                <th class="px-4 py-2.5 text-center font-medium text-muted-foreground w-16">
                                    <Eye class="mx-auto size-3.5" />
                                </th>
                                <th class="px-4 py-2.5 text-center font-medium text-muted-foreground w-16">
                                    <ThumbsUp class="mx-auto size-3.5" />
                                </th>
                                <th class="px-4 py-2.5 text-center font-medium text-muted-foreground w-20">Trạng thái</th>
                                <th class="px-4 py-2.5 text-right font-medium text-muted-foreground w-24">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="item in items" :key="item.id">
                                <!-- Main row -->
                                <tr
                                    class="border-b transition hover:bg-muted/30 cursor-pointer"
                                    @click="toggleRow(item.id)"
                                >
                                    <td class="px-4 py-2.5">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="categoryColor(item.category)"
                                        >
                                            {{ categoryLabel(item.category) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-medium">{{ item.question }}</span>
                                            <component
                                                :is="expandedRows.includes(item.id) ? ChevronUp : ChevronDown"
                                                class="size-3.5 shrink-0 text-muted-foreground"
                                            />
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center text-muted-foreground">{{ item.view_count }}</td>
                                    <td class="px-4 py-2.5 text-center text-muted-foreground">{{ item.helpful_count }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <Badge :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'" class="text-xs">
                                            {{ item.is_active ? 'Bật' : 'Tắt' }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-2.5 text-right" @click.stop>
                                        <div class="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="icon" class="size-7" @click="openEdit(item)" title="Sửa">
                                                <Edit2 class="size-3.5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" class="size-7 text-red-500 hover:text-red-600" @click="deleteItem(item)" title="Xóa">
                                                <Trash2 class="size-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Expanded row -->
                                <tr v-if="expandedRows.includes(item.id)" class="bg-muted/20">
                                    <td colspan="6" class="px-6 py-3">
                                        <div class="grid gap-3 text-sm sm:grid-cols-2">
                                            <div>
                                                <p class="mb-1 font-medium text-muted-foreground">Câu trả lời:</p>
                                                <p class="whitespace-pre-wrap text-foreground">{{ item.answer }}</p>
                                            </div>
                                            <div class="flex flex-col gap-2">
                                                <div v-if="item.alt_questions?.length">
                                                    <p class="mb-1 font-medium text-muted-foreground">Câu hỏi tương đương:</p>
                                                    <ul class="list-inside list-disc text-xs text-muted-foreground">
                                                        <li v-for="(q, i) in item.alt_questions" :key="i">{{ q }}</li>
                                                    </ul>
                                                </div>
                                                <div v-if="item.keywords?.length">
                                                    <p class="mb-1 font-medium text-muted-foreground">Từ khoá:</p>
                                                    <div class="flex flex-wrap gap-1">
                                                        <span
                                                            v-for="(kw, i) in item.keywords"
                                                            :key="i"
                                                            class="rounded bg-muted px-1.5 py-0.5 text-xs"
                                                        >{{ kw }}</span>
                                                    </div>
                                                </div>
                                                <div v-if="item.suggested_questions?.length">
                                                    <p class="mb-1 font-medium text-muted-foreground">Câu hỏi gợi ý:</p>
                                                    <ul class="list-inside list-disc text-xs text-muted-foreground">
                                                        <li v-for="(q, i) in item.suggested_questions" :key="i">{{ q }}</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <tr v-if="!items.length">
                                <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">
                                    <MessageSquare class="mx-auto mb-2 size-8 opacity-30" />
                                    <p>Không có dữ liệu</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
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
        <div v-if="showDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeDialog">
            <div class="flex w-full max-w-2xl flex-col gap-4 rounded-2xl bg-background p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">
                        {{ editingItem ? 'Sửa câu hỏi' : 'Thêm câu hỏi mới' }}
                    </h2>
                    <Button variant="ghost" size="icon" class="size-7" @click="closeDialog">
                        <X class="size-4" />
                    </Button>
                </div>

                <form @submit.prevent="submitForm" class="flex flex-col gap-4">
                    <!-- Category + display_order -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label>Danh mục <span class="text-red-500">*</span></Label>
                            <Input v-model="form.category" placeholder="vd: gia-ca" />
                            <p v-if="form.errors.category" class="text-xs text-red-500">{{ form.errors.category }}</p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Thứ tự hiển thị</Label>
                            <Input v-model.number="form.display_order" type="number" min="0" max="9999" />
                        </div>
                    </div>

                    <!-- Question -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Câu hỏi chính <span class="text-red-500">*</span></Label>
                        <Input v-model="form.question" placeholder="Làm sao để đăng ký tài khoản?" />
                        <p v-if="form.errors.question" class="text-xs text-red-500">{{ form.errors.question }}</p>
                    </div>

                    <!-- Answer -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Câu trả lời <span class="text-red-500">*</span></Label>
                        <textarea
                            v-model="form.answer"
                            placeholder="Nhập câu trả lời..."
                            rows="4"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        />
                        <p v-if="form.errors.answer" class="text-xs text-red-500">{{ form.errors.answer }}</p>
                    </div>

                    <!-- Alt questions -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Câu hỏi tương đương <span class="text-xs text-muted-foreground">(mỗi dòng một câu)</span></Label>
                        <textarea
                            v-model="altQuestionsText"
                            placeholder="Tôi muốn tạo tài khoản&#10;Đăng ký như thế nào"
                            rows="3"
                            class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        />
                    </div>

                    <!-- Keywords -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Từ khoá <span class="text-xs text-muted-foreground">(phân cách bằng dấu phẩy)</span></Label>
                        <Input v-model="keywordsText" placeholder="đăng ký, tài khoản, tạo mới" />
                    </div>

                    <!-- Suggested questions -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Câu hỏi gợi ý tiếp theo <span class="text-xs text-muted-foreground">(mỗi dòng một câu)</span></Label>
                        <textarea
                            v-model="suggestedQuestionsText"
                            placeholder="Gói dịch vụ có những loại nào?&#10;Chi phí sử dụng là bao nhiêu?"
                            rows="3"
                            class="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        />
                    </div>

                    <!-- is_active -->
                    <div class="flex items-center gap-2">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="size-4 rounded border-border"
                        />
                        <Label for="is_active" class="cursor-pointer">Kích hoạt (hiển thị trong chatbot)</Label>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="closeDialog">Hủy</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editingItem ? 'Cập nhật' : 'Thêm mới' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>
</template>
