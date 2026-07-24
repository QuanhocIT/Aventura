<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    Edit2,
    Eye,
    Newspaper,
    Plus,
    RefreshCcw,
    Star,
    StarOff,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    PageHeader,
    StatCard,
    StatusBadge,
    FilterBar,
    Pagination,
    EmptyState,
} from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface NewsPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    category: string;
    featured_image_url: string | null;
    is_published: boolean;
    is_featured: boolean;
    view_count: number;
    published_at: string | null;
    author_name?: string;
}

interface PaginatedPosts {
    data: NewsPost[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    posts: PaginatedPosts;
    filters: { search?: string; category?: string; status?: string };
    stats: {
        total: number;
        published: number;
        featured: number;
        total_views: number;
    };
}>();

// ── Filters ────────────────────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category || 'all');
const status = ref(props.filters.status || 'all');

function applyFilters() {
    router.get(
        '/super-admin/news',
        {
            search: search.value || undefined,
            category: category.value && category.value !== 'all' ? category.value : undefined,
            status: status.value && status.value !== 'all' ? status.value : undefined,
        },
        { preserveState: true, replace: true },
    );
}

// ── Dialog ─────────────────────────────────────────────────────────────────────
const showDialog = ref(false);
const editingPost = ref<NewsPost | null>(null);
const imagePreview = ref<string | null>(null);
const tagsText = ref('');

type FormData = {
    title: string;
    excerpt: string;
    content: string;
    category: string;
    tags: string[];
    is_published: boolean;
    is_featured: boolean;
    published_at: string;
    image: File | null;
};

const form = useForm<FormData>({
    title: '',
    excerpt: '',
    content: '',
    category: 'tin-tuc',
    tags: [],
    is_published: false,
    is_featured: false,
    published_at: '',
    image: null,
});

function openCreate() {
    editingPost.value = null;
    form.reset();
    form.category = 'tin-tuc';
    imagePreview.value = null;
    tagsText.value = '';
    showDialog.value = true;
}

function openEdit(post: NewsPost) {
    editingPost.value = post;
    form.title = post.title;
    form.excerpt = post.excerpt ?? '';
    form.content = '';
    form.category = post.category;
    form.is_published = post.is_published;
    form.is_featured = post.is_featured;
    form.published_at = '';
    form.image = null;
    imagePreview.value = post.featured_image_url;
    tagsText.value = '';
    showDialog.value = true;
    // Load full content via separate fetch
    fetch(`/super-admin/news/${post.id}/content`)
        .then((r) => r.json())
        .then((d) => {
            form.content = d.content ?? '';
            tagsText.value = (d.tags ?? []).join(', ');
        })
        .catch(() => {});
}

function closeDialog() {
    showDialog.value = false;
    editingPost.value = null;
}

function onImageChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    form.image = file;
    const reader = new FileReader();
    reader.onload = () => {
        imagePreview.value = reader.result as string;
    };
    reader.readAsDataURL(file);
}

function submitForm() {
    form.tags = tagsText.value
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeDialog,
    };

    if (editingPost.value) {
        form.put(`/super-admin/news/${editingPost.value.id}`, options as any);
    } else {
        form.post('/super-admin/news', options as any);
    }
}

async function deletePost(post: NewsPost) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa bài: "${post.title}"?`,
        }))
    ) {
        return;
    }

    router.delete(`/super-admin/news/${post.id}`, { preserveScroll: true });
}

function togglePublish(post: NewsPost) {
    router.patch(
        `/super-admin/news/${post.id}/publish`,
        {},
        { preserveScroll: true },
    );
}

function toggleFeatured(post: NewsPost) {
    router.patch(
        `/super-admin/news/${post.id}/featured`,
        {},
        { preserveScroll: true },
    );
}

// ── Category helpers ────────────────────────────────────────────────────────────
const categoryOptions = [
    { value: 'tin-tuc', label: 'Tin tức' },
    { value: 'khuyen-mai', label: 'Khuyến mãi' },
    { value: 'thanh-cong', label: 'Thành công' },
    { value: 'cap-nhat', label: 'Cập nhật' },
    { value: 'thong-bao', label: 'Thông báo' },
];
const categoryColors: Record<string, string> = {
    'tin-tuc': 'bg-blue-100 text-blue-700',
    'khuyen-mai': 'bg-green-100 text-green-700',
    'thanh-cong': 'bg-purple-100 text-purple-700',
    'cap-nhat': 'bg-amber-100 text-amber-700',
    'thong-bao': 'bg-red-100 text-red-700',
};
function catLabel(val: string) {
    return categoryOptions.find((o) => o.value === val)?.label ?? val;
}

const categoryStats = computed(() => {
    const counts: Record<string, number> = {
        'tin-tuc': 0,
        'khuyen-mai': 0,
        'thanh-cong': 0,
        'cap-nhat': 0,
        'thong-bao': 0,
    };
    const totalPosts = props.posts.data.length;
    props.posts.data.forEach((p) => {
        if (counts[p.category] !== undefined) {
            counts[p.category]++;
        }
    });

    return categoryOptions.map((opt) => {
        const count = counts[opt.value] || 0;
        const percent =
            totalPosts > 0 ? Math.round((count / totalPosts) * 100) : 0;

        return {
            ...opt,
            count,
            percent,
        };
    });
});

const avgViewsPerPost = computed(() => {
    if (props.stats.published === 0) {
        return 0;
    }

    return Math.round(props.stats.total_views / props.stats.published);
});

const draftRatio = computed(() => {
    if (props.stats.total === 0) {
        return 0;
    }

    const drafts = props.stats.total - props.stats.published;

    return Math.round((drafts / props.stats.total) * 100);
});

const featuredRatio = computed(() => {
    if (props.stats.total === 0) {
        return 0;
    }

    return Math.round((props.stats.featured / props.stats.total) * 100);
});

const engagementGrade = computed(() => {
    const avg = avgViewsPerPost.value;

    if (avg >= 100) {
        return 'Xuất sắc';
    }

    if (avg >= 20) {
        return 'Khá tốt';
    }

    return 'Cần cải thiện';
});

const hasFilters = computed(
    () => !!search.value || !!category.value || !!status.value,
);
</script>

<template>
    <Head title="Quản lý Tin tức" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Quản lý Tin tức"
            subtitle="Đăng bài, khuyến mãi, câu chuyện thành công"
            :icon="Newspaper"
        >
            <template #actions>
                <Button
                    size="sm"
                    @click="openCreate"
                    class="h-9 cursor-pointer rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 font-bold text-white shadow-xs hover:from-orange-600 hover:to-amber-600"
                >
                    <Plus class="mr-1.5 size-4" />
                    Viết bài mới
                </Button>
            </template>
        </PageHeader>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <!-- Total Posts -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Tổng bài viết
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-sky-500"
                    >
                        {{ stats.total }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-sky-500/20 bg-sky-500/10 text-sky-500"
                >
                    <Newspaper class="size-4.5" />
                </div>
            </div>

            <!-- Published -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Đã đăng
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-emerald-500"
                    >
                        {{ stats.published }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                >
                    <Eye class="size-4.5" />
                </div>
            </div>

            <!-- Featured -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Nổi bật
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-amber-500"
                    >
                        {{ stats.featured }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10 text-amber-500"
                >
                    <Star class="size-4.5" />
                </div>
            </div>

            <!-- Views -->
            <div
                class="shadow-3xs flex items-center justify-between rounded-2xl border border-border/40 bg-card/40 p-4 backdrop-blur-md transition-all duration-300 hover:shadow-xs"
            >
                <div>
                    <p
                        class="text-[9px] font-black tracking-wider text-muted-foreground uppercase"
                    >
                        Lượt xem
                    </p>
                    <h3
                        class="mt-1 font-mono text-2xl font-black tracking-tight text-violet-500"
                    >
                        {{ stats.total_views }}
                    </h3>
                </div>
                <div
                    class="flex size-9 items-center justify-center rounded-xl border border-violet-500/20 bg-violet-500/10 text-violet-500"
                >
                    <Eye class="size-4.5" />
                </div>
            </div>
        </div>

        <!-- Main Layout Grid -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <!-- Left Column: Filters and Table -->
            <div class="space-y-5 lg:col-span-3">
                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <Input
                        v-model="search"
                        placeholder="Tìm tiêu đề..."
                        class="h-9 w-full rounded-xl border-border bg-background text-xs sm:w-56"
                        @keydown.enter="applyFilters"
                    />
                    <Select
                        v-model="category"
                        @update:model-value="applyFilters"
                    >
                        <SelectTrigger
                            class="h-9 w-40 rounded-xl border-border bg-background text-xs"
                            ><SelectValue placeholder="Tất cả danh mục"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả danh mục</SelectItem>
                            <SelectItem
                                v-for="opt in categoryOptions"
                                :key="opt.value"
                                :value="opt.value"
                                >{{ opt.label }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                    <Select v-model="status" @update:model-value="applyFilters">
                        <SelectTrigger
                            class="h-9 w-36 rounded-xl border-border bg-background text-xs"
                            ><SelectValue placeholder="Tất cả trạng thái"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tất cả</SelectItem>
                            <SelectItem value="published">Đã đăng</SelectItem>
                            <SelectItem value="draft">Bản nháp</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        size="sm"
                        class="h-9 rounded-xl border border-border/40 bg-muted/60 px-4 text-xs font-bold text-foreground hover:bg-muted"
                        @click="applyFilters"
                        >Tìm</Button
                    >
                    <Button
                        v-if="hasFilters"
                        variant="ghost"
                        size="sm"
                        class="h-9 rounded-xl text-xs text-rose-500 hover:bg-rose-500/10 hover:text-rose-600"
                        @click="
                            () => {
                                search = '';
                                category = '';
                                status = '';
                                applyFilters();
                            }
                        "
                    >
                        <X class="mr-1 size-3.5" />Xóa lọc
                    </Button>
                </div>

                <!-- Table Card -->
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr
                                        class="border-b border-border/40 bg-muted/10"
                                    >
                                        <th
                                            class="px-4 py-3 text-left text-xs font-black tracking-wider text-muted-foreground uppercase"
                                        >
                                            Bài viết
                                        </th>
                                        <th
                                            class="w-28 px-4 py-3 text-left text-xs font-black tracking-wider text-muted-foreground uppercase"
                                        >
                                            Danh mục
                                        </th>
                                        <th
                                            class="w-24 px-4 py-3 text-center text-xs font-black tracking-wider text-muted-foreground uppercase"
                                        >
                                            Trạng thái
                                        </th>
                                        <th
                                            class="w-20 px-4 py-3 text-center text-xs font-black tracking-wider text-muted-foreground uppercase"
                                        >
                                            <Eye class="mx-auto size-4" />
                                        </th>
                                        <th
                                            class="w-24 px-4 py-3 text-center text-xs font-black tracking-wider text-muted-foreground uppercase"
                                        >
                                            Ngày đăng
                                        </th>
                                        <th
                                            class="w-28 px-4 py-3 text-right text-xs font-black tracking-wider text-muted-foreground uppercase"
                                        >
                                            Thao tác
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="post in posts.data"
                                        :key="post.id"
                                        class="border-b border-border/30 transition hover:bg-muted/20"
                                    >
                                        <!-- Title + thumbnail -->
                                        <td class="px-4 py-3">
                                            <div
                                                class="flex items-center gap-3"
                                            >
                                                <div
                                                    class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-border bg-muted"
                                                >
                                                    <img
                                                        v-if="
                                                            post.featured_image_url
                                                        "
                                                        :src="
                                                            post.featured_image_url
                                                        "
                                                        class="h-full w-full object-cover"
                                                    />
                                                    <div
                                                        v-else
                                                        class="flex h-full items-center justify-center text-muted-foreground/30"
                                                    >
                                                        <Newspaper
                                                            class="size-4"
                                                        />
                                                    </div>
                                                </div>
                                                <div>
                                                    <p
                                                        class="line-clamp-1 text-xs leading-snug font-bold text-slate-800 dark:text-slate-200"
                                                    >
                                                        {{ post.title }}
                                                    </p>
                                                    <p
                                                        v-if="post.excerpt"
                                                        class="mt-0.5 line-clamp-1 text-[10px] font-semibold text-muted-foreground"
                                                    >
                                                        {{ post.excerpt }}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Category -->
                                        <td class="px-4 py-3">
                                            <span
                                                v-if="
                                                    post.category === 'tin-tuc'
                                                "
                                                class="rounded-md border border-blue-500/20 bg-blue-500/10 px-2 py-0.5 text-[10px] font-bold text-blue-600"
                                            >
                                                Tin tức
                                            </span>
                                            <span
                                                v-else-if="
                                                    post.category ===
                                                    'khuyen-mai'
                                                "
                                                class="rounded-md border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-600"
                                            >
                                                Khuyến mãi
                                            </span>
                                            <span
                                                v-else-if="
                                                    post.category ===
                                                    'thanh-cong'
                                                "
                                                class="rounded-md border border-purple-500/20 bg-purple-500/10 px-2 py-0.5 text-[10px] font-bold text-purple-600"
                                            >
                                                Thành công
                                            </span>
                                            <span
                                                v-else-if="
                                                    post.category === 'cap-nhat'
                                                "
                                                class="rounded-md border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold text-amber-600"
                                            >
                                                Cập nhật
                                            </span>
                                            <span
                                                v-else-if="
                                                    post.category ===
                                                    'thong-bao'
                                                "
                                                class="rounded-md border border-red-500/20 bg-red-500/10 px-2 py-0.5 text-[10px] font-bold text-red-600"
                                            >
                                                Thông báo
                                            </span>
                                            <span
                                                v-else
                                                class="rounded-md border border-slate-500/20 bg-slate-500/10 px-2 py-0.5 text-[10px] font-bold text-slate-600"
                                            >
                                                {{ catLabel(post.category) }}
                                            </span>
                                        </td>
                                        <!-- Status -->
                                        <td class="px-4 py-3 text-center">
                                            <button
                                                @click="togglePublish(post)"
                                                :class="
                                                    post.is_published
                                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                                        : 'border-slate-500/20 bg-slate-500/10 text-slate-500'
                                                "
                                                class="cursor-pointer rounded-full border px-2.5 py-0.5 text-[10px] font-bold transition hover:opacity-80"
                                            >
                                                {{
                                                    post.is_published
                                                        ? 'Đã đăng'
                                                        : 'Nháp'
                                                }}
                                            </button>
                                        </td>
                                        <!-- Views -->
                                        <td
                                            class="px-4 py-3 text-center font-mono text-xs font-bold text-muted-foreground"
                                        >
                                            {{ post.view_count }}
                                        </td>
                                        <!-- Date -->
                                        <td class="px-4 py-3 text-center">
                                            <span
                                                v-if="post.published_at"
                                                class="flex items-center justify-center gap-1 font-mono text-[11px] font-bold text-muted-foreground"
                                            >
                                                <Calendar class="size-3" />{{
                                                    post.published_at
                                                }}
                                            </span>
                                            <span
                                                v-else
                                                class="text-[11px] font-bold text-muted-foreground"
                                                >-</span
                                            >
                                        </td>
                                        <!-- Actions -->
                                        <td class="px-4 py-3 text-right">
                                            <div
                                                class="flex items-center justify-end gap-1.5"
                                            >
                                                <button
                                                    @click="
                                                        toggleFeatured(post)
                                                    "
                                                    :title="
                                                        post.is_featured
                                                            ? 'Bỏ ghim'
                                                            : 'Ghim nổi bật'
                                                    "
                                                    :class="
                                                        post.is_featured
                                                            ? 'text-amber-500 hover:text-amber-600'
                                                            : 'text-muted-foreground hover:text-amber-500'
                                                    "
                                                    class="cursor-pointer rounded p-1 transition"
                                                >
                                                    <component
                                                        :is="
                                                            post.is_featured
                                                                ? Star
                                                                : StarOff
                                                        "
                                                        class="size-4"
                                                    />
                                                </button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-7 cursor-pointer rounded-lg text-muted-foreground hover:bg-muted/80 hover:text-foreground"
                                                    @click="openEdit(post)"
                                                    title="Sửa"
                                                >
                                                    <Edit2 class="size-3.5" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-7 cursor-pointer rounded-lg text-rose-500 hover:bg-rose-500/10 hover:text-rose-600"
                                                    @click="deletePost(post)"
                                                    title="Xóa"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="!posts.data.length">
                                        <td
                                            colspan="6"
                                            class="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            <Newspaper
                                                class="mx-auto mb-2 size-8 opacity-30"
                                            />
                                            <p class="text-xs font-bold">
                                                Chưa có bài viết nào
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination -->
                        <div
                            v-if="posts.last_page > 1"
                            class="flex items-center justify-between border-t border-border/40 px-4 py-3 text-xs"
                        >
                            <p class="font-bold text-muted-foreground">
                                Tổng
                                <span
                                    class="font-mono font-extrabold text-foreground"
                                    >{{ posts.total }}</span
                                >
                                bài
                            </p>
                            <div class="flex gap-1.5">
                                <template
                                    v-for="link in posts.links"
                                    :key="link.label"
                                >
                                    <button
                                        v-if="link.url"
                                        @click="router.get(link.url)"
                                        :class="
                                            link.active
                                                ? 'bg-orange-500 font-extrabold text-white shadow-sm'
                                                : 'font-bold text-foreground hover:bg-muted'
                                        "
                                        class="min-w-8 cursor-pointer rounded-lg px-2.5 py-1 text-[11px] transition"
                                        v-html="link.label"
                                    />
                                </template>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Column: Charts & AI Advisor -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Category distribution chart -->
                <Card
                    class="overflow-hidden rounded-2xl border border-border/40 bg-card/45 shadow-2xs backdrop-blur-md"
                >
                    <CardContent class="space-y-4 p-5">
                        <div class="flex items-center justify-between">
                            <h4
                                class="text-xs font-black tracking-wider text-muted-foreground uppercase"
                            >
                                Phân bổ chuyên mục bài viết
                            </h4>
                            <span
                                class="rounded bg-orange-500/10 p-1 text-[10px] font-bold text-orange-500"
                                >Tỷ lệ %</span
                            >
                        </div>
                        <div class="space-y-3.5">
                            <div
                                v-for="stat in categoryStats"
                                :key="stat.value"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span
                                        class="font-bold text-slate-700 dark:text-slate-300"
                                        >{{ stat.label }}</span
                                    >
                                    <span
                                        class="font-mono font-black text-slate-800 dark:text-slate-200"
                                    >
                                        {{ stat.count }} bài ({{
                                            stat.percent
                                        }}%)
                                    </span>
                                </div>
                                <div
                                    class="relative h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500 ease-out"
                                        :class="
                                            stat.value === 'tin-tuc'
                                                ? 'bg-gradient-to-r from-blue-500 to-sky-400'
                                                : stat.value === 'khuyen-mai'
                                                  ? 'bg-gradient-to-r from-emerald-500 to-teal-400'
                                                  : stat.value === 'thanh-cong'
                                                    ? 'bg-gradient-to-r from-purple-500 to-indigo-400'
                                                    : stat.value === 'cap-nhat'
                                                      ? 'bg-gradient-to-r from-amber-500 to-orange-400'
                                                      : 'bg-gradient-to-r from-rose-500 to-red-400'
                                        "
                                        :style="`width: ${stat.percent}%`"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Content Evaluation & AI Strategy Advisor Card -->
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
                                Đo lường & Gợi ý từ AI
                            </h4>
                            <span
                                class="animate-pulse rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                :class="
                                    engagementGrade === 'Xuất sắc'
                                        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600'
                                        : engagementGrade === 'Khá tốt'
                                          ? 'border-blue-500/20 bg-blue-500/10 text-blue-600'
                                          : 'border-rose-500/20 bg-rose-500/10 text-rose-600'
                                "
                            >
                                Độc giả: {{ engagementGrade }}
                            </span>
                        </div>

                        <!-- Statistics Assessment Row -->
                        <div class="grid grid-cols-3 gap-2.5">
                            <div
                                class="rounded-xl border border-border/30 bg-muted/10 p-2.5 text-center"
                            >
                                <span
                                    class="block text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Index tương tác</span
                                >
                                <span
                                    class="mt-1 block font-mono text-xs font-black text-slate-800 dark:text-slate-200"
                                    >{{ avgViewsPerPost }}
                                    <span
                                        class="block text-center text-[8px] font-bold text-muted-foreground"
                                        >views/bài</span
                                    ></span
                                >
                            </div>
                            <div
                                class="rounded-xl border border-border/30 bg-muted/10 p-2.5 text-center"
                            >
                                <span
                                    class="block text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Tỷ lệ nổi bật</span
                                >
                                <span
                                    class="mt-1 block font-mono text-xs font-black text-slate-800 dark:text-slate-200"
                                    >{{ featuredRatio }}%</span
                                >
                            </div>
                            <div
                                class="rounded-xl border border-border/30 bg-muted/10 p-2.5 text-center"
                            >
                                <span
                                    class="block text-[9px] font-bold tracking-wider text-muted-foreground uppercase"
                                    >Tỷ lệ bản nháp</span
                                >
                                <span
                                    class="mt-1 block font-mono text-xs font-black text-slate-800 dark:text-slate-200"
                                    >{{ draftRatio }}%</span
                                >
                            </div>
                        </div>

                        <!-- AI CRM Suggestions -->
                        <div class="space-y-3.5 pt-2">
                            <!-- Suggestion 1: Low average views -->
                            <div
                                v-if="avgViewsPerPost < 30"
                                class="flex items-start gap-2.5 rounded-xl border border-orange-500/20 bg-orange-500/[0.03] p-3"
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg bg-orange-500/10 p-1 text-orange-500"
                                >
                                    <Percent class="size-4.5" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Tối ưu hóa sức hút bài viết
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal text-muted-foreground"
                                    >
                                        Lượt xem trung bình đang ở mức thấp ({{
                                            avgViewsPerPost
                                        }}
                                        views/bài). Hãy cải thiện tiêu đề với từ
                                        khóa gây tò mò hoặc đính kèm tag chiến
                                        dịch quảng bá.
                                    </p>
                                </div>
                            </div>

                            <!-- Suggestion 2: High drafts count -->
                            <div
                                v-if="draftRatio > 40"
                                class="flex items-start gap-2.5 rounded-xl border border-blue-500/20 bg-blue-500/[0.03] p-3"
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg bg-blue-500/10 p-1 text-blue-500"
                                >
                                    <Clock class="size-4.5" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Kế hoạch đăng tải bản nháp
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal text-muted-foreground"
                                    >
                                        Hệ thống đang lưu trữ {{ draftRatio }}%
                                        bài viết nháp. Hãy thiết lập khung giờ
                                        vàng (11:00 hoặc 18:00) để xuất bản các
                                        bài viết này.
                                    </p>
                                </div>
                            </div>

                            <!-- Suggestion 3: Missing case study/promotions -->
                            <div
                                v-if="
                                    !categoryStats.find(
                                        (s) => s.value === 'khuyen-mai',
                                    )?.count
                                "
                                class="flex items-start gap-2.5 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.03] p-3"
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg bg-emerald-500/10 p-1 text-emerald-500"
                                >
                                    <TrendingUp class="size-4.5" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Tạo tin tức Khuyến mãi
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal text-muted-foreground"
                                    >
                                        Chưa có bài viết khuyến mãi nào. Hãy
                                        xuất bản thêm tin ưu đãi/giảm giá gói
                                        cước để kích thích nhà hàng gia hạn dịch
                                        vụ.
                                    </p>
                                </div>
                            </div>

                            <!-- Suggestion 4: General good performance -->
                            <div
                                v-if="avgViewsPerPost >= 30 && draftRatio <= 40"
                                class="flex items-start gap-2.5 rounded-xl border border-emerald-500/20 bg-emerald-500/[0.03] p-3"
                            >
                                <div
                                    class="mt-0.5 shrink-0 rounded-lg bg-emerald-500/10 p-1 text-emerald-500"
                                >
                                    <CheckCircle class="size-4.5" />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        Chất lượng phân phối ổn định
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] leading-normal text-muted-foreground"
                                    >
                                        Các chỉ số tương tác đều đạt mức tiêu
                                        chuẩn. Duy trì việc ghim bài viết nổi
                                        bật vừa phải để tránh làm nhiễu tiêu
                                        điểm độc giả.
                                    </p>
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
        <div
            v-if="showDialog"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 pt-10 backdrop-blur-xs"
        >
            <div
                class="mb-10 flex w-full max-w-2xl flex-col gap-5 rounded-2xl border border-border/40 bg-card/90 p-6 shadow-2xl backdrop-blur-md"
            >
                <div
                    class="flex items-center justify-between border-b border-border/40 pb-3"
                >
                    <div class="flex flex-col gap-0.5">
                        <h2
                            class="text-sm font-black tracking-wide text-slate-800 dark:text-slate-100"
                        >
                            {{
                                editingPost
                                    ? 'Chỉnh sửa bài viết'
                                    : 'Viết bài mới'
                            }}
                        </h2>
                        <p
                            class="text-[10px] font-semibold text-muted-foreground"
                        >
                            Tạo hoặc sửa đổi tin tức, khuyến mãi trên hệ thống
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 cursor-pointer rounded-lg text-muted-foreground hover:text-foreground"
                        @click="closeDialog"
                        ><X class="size-4"
                    /></Button>
                </div>

                <form @submit.prevent="submitForm" class="flex flex-col gap-4">
                    <!-- Top section: image upload & categories / checks -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <!-- Image upload -->
                        <div class="flex flex-col gap-1.5">
                            <Label
                                class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                >Ảnh đại diện</Label
                            >
                            <label
                                for="image-upload"
                                class="group relative flex h-[120px] w-full cursor-pointer flex-col items-center justify-center gap-1.5 overflow-hidden rounded-xl border border-dashed border-border bg-muted/10 p-3 transition-all hover:border-orange-500/50"
                            >
                                <input
                                    type="file"
                                    id="image-upload"
                                    accept="image/jpg,image/jpeg,image/png,image/webp"
                                    class="hidden"
                                    @change="onImageChange"
                                />
                                <img
                                    v-if="imagePreview"
                                    :src="imagePreview"
                                    class="absolute inset-0 h-full w-full rounded-xl object-cover"
                                />
                                <div
                                    v-if="imagePreview"
                                    class="absolute inset-0 flex items-center justify-center rounded-xl bg-black/40 opacity-0 transition-all group-hover:opacity-100"
                                >
                                    <Upload class="size-5 text-white" />
                                </div>
                                <template v-else>
                                    <Upload
                                        class="size-5 text-muted-foreground/60 transition-colors group-hover:text-orange-500"
                                    />
                                    <span
                                        class="text-[10px] font-black text-muted-foreground transition-colors group-hover:text-foreground"
                                        >Tải ảnh lên</span
                                    >
                                    <span
                                        class="text-[9px] text-muted-foreground/60"
                                        >JPG, PNG, WebP · Max 5MB</span
                                    >
                                </template>
                            </label>
                        </div>

                        <!-- Category + Featured -->
                        <div class="flex flex-col justify-between gap-3">
                            <div class="space-y-1.5">
                                <Label
                                    class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                                    >Danh mục
                                    <span class="text-rose-500">*</span></Label
                                >
                                <Select v-model="form.category">
                                    <SelectTrigger
                                        class="h-9 rounded-xl border border-border bg-background text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20"
                                        ><SelectValue
                                    /></SelectTrigger>
                                    <SelectContent class="rounded-xl">
                                        <SelectItem
                                            v-for="opt in categoryOptions"
                                            :key="opt.value"
                                            :value="opt.value"
                                            class="cursor-pointer text-xs font-semibold"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="size-2 rounded-full"
                                                    :class="
                                                        opt.value === 'tin-tuc'
                                                            ? 'bg-blue-500'
                                                            : opt.value ===
                                                                'khuyen-mai'
                                                              ? 'bg-emerald-500'
                                                              : opt.value ===
                                                                  'thanh-cong'
                                                                ? 'bg-purple-500'
                                                                : opt.value ===
                                                                    'cap-nhat'
                                                                  ? 'bg-amber-500'
                                                                  : 'bg-red-500'
                                                    "
                                                ></span>
                                                {{ opt.label }}
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div
                                class="space-y-1 rounded-xl border border-border/20 bg-muted/15 p-2"
                            >
                                <!-- Published iOS switch -->
                                <div
                                    class="flex items-center justify-between rounded-lg p-2 transition duration-150 hover:bg-muted/30"
                                >
                                    <div class="flex flex-col">
                                        <span
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Đăng bài viết ngay</span
                                        >
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Hiện trên bảng tin</span
                                        >
                                    </div>
                                    <button
                                        type="button"
                                        @click="
                                            form.is_published =
                                                !form.is_published
                                        "
                                        :class="
                                            form.is_published
                                                ? 'bg-orange-500'
                                                : 'bg-slate-300 dark:bg-slate-700'
                                        "
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    >
                                        <span
                                            :class="
                                                form.is_published
                                                    ? 'translate-x-4'
                                                    : 'translate-x-0'
                                            "
                                            class="pointer-events-none inline-block size-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                        ></span>
                                    </button>
                                </div>
                                <!-- Featured iOS switch -->
                                <div
                                    class="flex items-center justify-between rounded-lg p-2 transition duration-150 hover:bg-muted/30"
                                >
                                    <div class="flex flex-col">
                                        <span
                                            class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                            >Ghim nổi bật</span
                                        >
                                        <span
                                            class="text-[9px] font-semibold text-muted-foreground"
                                            >Đầu danh sách tin</span
                                        >
                                    </div>
                                    <button
                                        type="button"
                                        @click="
                                            form.is_featured = !form.is_featured
                                        "
                                        :class="
                                            form.is_featured
                                                ? 'bg-orange-500'
                                                : 'bg-slate-300 dark:bg-slate-700'
                                        "
                                        class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                    >
                                        <span
                                            :class="
                                                form.is_featured
                                                    ? 'translate-x-4'
                                                    : 'translate-x-0'
                                            "
                                            class="pointer-events-none inline-block size-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                                        ></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Title -->
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Tiêu đề bài viết
                            <span class="text-rose-500">*</span></Label
                        >
                        <Input
                            v-model="form.title"
                            placeholder="Nhập tiêu đề bài viết..."
                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        />
                        <p
                            v-if="form.errors.title"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.title }}
                        </p>
                    </div>

                    <!-- Excerpt -->
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Tóm tắt ngắn
                            <span class="text-[10px] text-muted-foreground"
                                >(Hiển thị trên danh sách)</span
                            ></Label
                        >
                        <textarea
                            v-model="form.excerpt"
                            rows="2"
                            placeholder="Mô tả ngắn gọn về nội dung bài viết..."
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground placeholder:text-muted-foreground focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 focus:outline-none"
                        />
                    </div>

                    <!-- Content -->
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Nội dung chi tiết
                            <span class="text-rose-500">*</span></Label
                        >
                        <textarea
                            v-model="form.content"
                            rows="5"
                            placeholder="Nội dung bài viết... Hỗ trợ định dạng in đậm bằng cú pháp **in đậm** và xuống dòng bằng phím Enter"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 font-mono text-xs font-semibold text-foreground placeholder:text-muted-foreground focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 focus:outline-none"
                        />
                        <p
                            v-if="form.errors.content"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.content }}
                        </p>
                        <p
                            class="text-[10px] leading-normal font-semibold text-muted-foreground"
                        >
                            Mẹo soạn thảo: Sử dụng **nội dung** để in đậm chữ,
                            gõ Enter để xuống dòng tự nhiên.
                        </p>
                    </div>

                    <!-- Tags -->
                    <div class="flex flex-col gap-1.5">
                        <Label
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                            >Từ khóa Tags
                            <span class="text-[10px] text-muted-foreground"
                                >(phân cách các thẻ bằng dấu phẩy)</span
                            ></Label
                        >
                        <Input
                            v-model="tagsText"
                            placeholder="Ví dụ: khuyến mãi, tính năng, cập nhật"
                            class="h-9 rounded-xl border-border bg-background text-xs font-semibold focus:border-orange-500 focus:ring-orange-500/20 focus-visible:border-orange-500 focus-visible:ring-orange-500/20"
                        />
                    </div>

                    <div
                        class="flex justify-end gap-2 border-t border-border/40 pt-3"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeDialog"
                            class="h-9 cursor-pointer rounded-xl border-border text-xs font-bold hover:bg-muted"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="h-9 cursor-pointer rounded-xl bg-gradient-to-r from-orange-500 to-amber-500 text-xs font-bold text-white shadow-xs transition-colors hover:from-orange-600 hover:to-amber-600"
                        >
                            {{ editingPost ? 'Lưu bài viết' : 'Đăng bài viết' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>
</template>
