<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calendar, Edit2, Eye, Newspaper, Plus,
    RefreshCcw, Star, StarOff, Trash2, Upload, X,
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
    stats: { total: number; published: number; featured: number; total_views: number };
}>();

// ── Filters ────────────────────────────────────────────────────────────────────
const search = ref(props.filters.search ?? '');
const category = ref(props.filters.category ?? '');
const status = ref(props.filters.status ?? '');

function applyFilters() {
    router.get('/super-admin/news', {
        search: search.value || undefined,
        category: category.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true, replace: true });
}

// ── Dialog ─────────────────────────────────────────────────────────────────────
const showDialog = ref(false);
const editingPost = ref<NewsPost | null>(null);
const imagePreview = ref<string | null>(null);
const tagsText = ref('');

type FormData = {
    title: string; excerpt: string; content: string;
    category: string; tags: string[]; is_published: boolean;
    is_featured: boolean; published_at: string; image: File | null;
};

const form = useForm<FormData>({
    title: '', excerpt: '', content: '', category: 'tin-tuc',
    tags: [], is_published: false, is_featured: false, published_at: '', image: null,
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
    fetch(`/super-admin/news/${post.id}/content`).then(r => r.json()).then(d => {
        form.content = d.content ?? '';
        tagsText.value = (d.tags ?? []).join(', ');
    }).catch(() => {});
}

function closeDialog() {
    showDialog.value = false;
    editingPost.value = null;
}

function onImageChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    form.image = file;
    const reader = new FileReader();
    reader.onload = () => { imagePreview.value = reader.result as string; };
    reader.readAsDataURL(file);
}

function submitForm() {
    form.tags = tagsText.value.split(',').map(s => s.trim()).filter(Boolean);
    const options = { preserveScroll: true, forceFormData: true, onSuccess: closeDialog };

    if (editingPost.value) {
        form.put(`/super-admin/news/${editingPost.value.id}`, options as any);
    } else {
        form.post('/super-admin/news', options as any);
    }
}

function deletePost(post: NewsPost) {
    if (!confirm(`Xóa bài: "${post.title}"?`)) return;
    router.delete(`/super-admin/news/${post.id}`, { preserveScroll: true });
}

function togglePublish(post: NewsPost) {
    router.patch(`/super-admin/news/${post.id}/publish`, {}, { preserveScroll: true });
}

function toggleFeatured(post: NewsPost) {
    router.patch(`/super-admin/news/${post.id}/featured`, {}, { preserveScroll: true });
}

// ── Category helpers ────────────────────────────────────────────────────────────
const categoryOptions = [
    { value: 'tin-tuc',    label: 'Tin tức' },
    { value: 'khuyen-mai', label: 'Khuyến mãi' },
    { value: 'thanh-cong', label: 'Thành công' },
    { value: 'cap-nhat',   label: 'Cập nhật' },
    { value: 'thong-bao',  label: 'Thông báo' },
];
const categoryColors: Record<string, string> = {
    'tin-tuc':    'bg-blue-100 text-blue-700',
    'khuyen-mai': 'bg-green-100 text-green-700',
    'thanh-cong': 'bg-purple-100 text-purple-700',
    'cap-nhat':   'bg-amber-100 text-amber-700',
    'thong-bao':  'bg-red-100 text-red-700',
};
function catLabel(val: string) {
    return categoryOptions.find(o => o.value === val)?.label ?? val;
}

const hasFilters = computed(() => !!search.value || !!category.value || !!status.value);
</script>

<template>
    <Head title="Quản lý Tin tức" />

    <div class="flex flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10">
                    <Newspaper class="size-5 text-primary" />
                </div>
                <div>
                    <h1 class="text-xl font-semibold">Quản lý Tin tức</h1>
                    <p class="text-sm text-muted-foreground">Đăng bài, khuyến mãi, câu chuyện thành công</p>
                </div>
            </div>
            <Button size="sm" @click="openCreate">
                <Plus class="mr-1.5 size-3.5" />
                Viết bài mới
            </Button>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <Card><CardContent class="pt-4"><p class="text-xs text-muted-foreground">Tổng bài</p><p class="text-2xl font-bold">{{ stats.total }}</p></CardContent></Card>
            <Card><CardContent class="pt-4"><p class="text-xs text-muted-foreground">Đã đăng</p><p class="text-2xl font-bold text-green-600">{{ stats.published }}</p></CardContent></Card>
            <Card><CardContent class="pt-4"><p class="text-xs text-muted-foreground">Nổi bật</p><p class="text-2xl font-bold text-amber-500">{{ stats.featured }}</p></CardContent></Card>
            <Card><CardContent class="pt-4"><p class="text-xs text-muted-foreground">Lượt xem</p><p class="text-2xl font-bold">{{ stats.total_views.toLocaleString() }}</p></CardContent></Card>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3">
            <Input v-model="search" placeholder="Tìm tiêu đề..." class="h-8 w-56 text-sm" @keydown.enter="applyFilters" />
            <Select v-model="category" @update:model-value="applyFilters">
                <SelectTrigger class="h-8 w-40 text-sm"><SelectValue placeholder="Tất cả danh mục" /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả danh mục</SelectItem>
                    <SelectItem v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="status" @update:model-value="applyFilters">
                <SelectTrigger class="h-8 w-36 text-sm"><SelectValue placeholder="Tất cả trạng thái" /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả</SelectItem>
                    <SelectItem value="published">Đã đăng</SelectItem>
                    <SelectItem value="draft">Bản nháp</SelectItem>
                </SelectContent>
            </Select>
            <Button variant="ghost" size="sm" class="h-8" @click="applyFilters">Tìm</Button>
            <Button v-if="hasFilters" variant="ghost" size="sm" class="h-8 text-muted-foreground" @click="() => { search = ''; category = ''; status = ''; applyFilters(); }">
                <X class="mr-1 size-3.5" />Xóa lọc
            </Button>
        </div>

        <!-- Table -->
        <Card>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th class="px-4 py-2.5 text-left font-medium text-muted-foreground">Bài viết</th>
                                <th class="px-4 py-2.5 text-left font-medium text-muted-foreground w-28">Danh mục</th>
                                <th class="px-4 py-2.5 text-center font-medium text-muted-foreground w-24">Trạng thái</th>
                                <th class="px-4 py-2.5 text-center font-medium text-muted-foreground w-20">
                                    <Eye class="mx-auto size-3.5" />
                                </th>
                                <th class="px-4 py-2.5 text-center font-medium text-muted-foreground w-24">Ngày đăng</th>
                                <th class="px-4 py-2.5 text-right font-medium text-muted-foreground w-28">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="post in posts.data" :key="post.id" class="border-b transition hover:bg-muted/20">
                                <!-- Title + thumbnail -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="size-12 shrink-0 overflow-hidden rounded-lg bg-muted">
                                            <img v-if="post.featured_image_url" :src="post.featured_image_url" class="h-full w-full object-cover" />
                                            <div v-else class="flex h-full items-center justify-center text-muted-foreground/30">
                                                <Newspaper class="size-5" />
                                            </div>
                                        </div>
                                        <div>
                                            <p class="font-medium leading-snug line-clamp-1">{{ post.title }}</p>
                                            <p v-if="post.excerpt" class="text-xs text-muted-foreground line-clamp-1 mt-0.5">{{ post.excerpt }}</p>
                                        </div>
                                    </div>
                                </td>
                                <!-- Category -->
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="categoryColors[post.category] ?? 'bg-slate-100 text-slate-700'">
                                        {{ catLabel(post.category) }}
                                    </span>
                                </td>
                                <!-- Status -->
                                <td class="px-4 py-3 text-center">
                                    <button @click="togglePublish(post)"
                                        :class="post.is_published ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                                        class="rounded-full px-2.5 py-0.5 text-xs font-medium transition hover:opacity-80">
                                        {{ post.is_published ? 'Đã đăng' : 'Nháp' }}
                                    </button>
                                </td>
                                <!-- Views -->
                                <td class="px-4 py-3 text-center text-muted-foreground">{{ post.view_count }}</td>
                                <!-- Date -->
                                <td class="px-4 py-3 text-center">
                                    <span v-if="post.published_at" class="flex items-center justify-center gap-1 text-xs text-muted-foreground">
                                        <Calendar class="size-3" />{{ post.published_at }}
                                    </span>
                                    <span v-else class="text-xs text-muted-foreground"></span>
                                </td>
                                <!-- Actions -->
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="toggleFeatured(post)" :title="post.is_featured ? 'Bỏ ghim' : 'Ghim nổi bật'"
                                            :class="post.is_featured ? 'text-amber-500' : 'text-muted-foreground hover:text-amber-500'"
                                            class="rounded p-1 transition">
                                            <component :is="post.is_featured ? Star : StarOff" class="size-3.5" />
                                        </button>
                                        <Button variant="ghost" size="icon" class="size-7" @click="openEdit(post)" title="Sửa">
                                            <Edit2 class="size-3.5" />
                                        </Button>
                                        <Button variant="ghost" size="icon" class="size-7 text-red-500 hover:text-red-600" @click="deletePost(post)" title="Xóa">
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!posts.data.length">
                                <td colspan="6" class="px-4 py-12 text-center text-muted-foreground">
                                    <Newspaper class="mx-auto mb-2 size-8 opacity-30" />
                                    <p>Chưa có bài viết nào</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div v-if="posts.last_page > 1" class="flex items-center justify-between border-t px-4 py-3 text-sm">
                    <p class="text-muted-foreground">Tổng {{ posts.total }} bài</p>
                    <div class="flex gap-1">
                        <template v-for="link in posts.links" :key="link.label">
                            <button v-if="link.url" @click="router.get(link.url)"
                                :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted text-foreground'"
                                class="min-w-8 rounded px-2 py-1 text-xs transition" v-html="link.label" />
                        </template>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <!-- Dialog: Create / Edit -->
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0"
        enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="showDialog" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-10">
            <div class="flex w-full max-w-2xl flex-col gap-5 rounded-2xl bg-background p-6 shadow-2xl mb-10">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold">{{ editingPost ? 'Sửa bài viết' : 'Viết bài mới' }}</h2>
                    <Button variant="ghost" size="icon" class="size-7" @click="closeDialog"><X class="size-4" /></Button>
                </div>

                <form @submit.prevent="submitForm" class="flex flex-col gap-4">
                    <!-- Image upload -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Ảnh đại diện</Label>
                        <div class="flex items-start gap-4">
                            <div class="size-28 shrink-0 overflow-hidden rounded-xl border-2 border-dashed border-border bg-muted flex items-center justify-center">
                                <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" />
                                <Upload v-else class="size-6 text-muted-foreground/50" />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <input type="file" id="image-upload" accept="image/jpg,image/jpeg,image/png,image/webp"
                                    class="hidden" @change="onImageChange" />
                                <Label for="image-upload" class="cursor-pointer inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm hover:bg-muted transition">
                                    <Upload class="size-3.5" /> Chọn ảnh
                                </Label>
                                <p class="text-xs text-muted-foreground">JPG, PNG, WebP · Tối đa 5MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- Category + Featured -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <Label>Danh mục <span class="text-red-500">*</span></Label>
                            <Select v-model="form.category">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col gap-3 pt-6">
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" v-model="form.is_published" class="size-4 rounded" />
                                Đăng ngay
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer text-sm">
                                <input type="checkbox" v-model="form.is_featured" class="size-4 rounded" />
                                Ghim nổi bật
                            </label>
                        </div>
                    </div>

                    <!-- Title -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Tiêu đề <span class="text-red-500">*</span></Label>
                        <Input v-model="form.title" placeholder="Nhập tiêu đề bài viết..." />
                        <p v-if="form.errors.title" class="text-xs text-red-500">{{ form.errors.title }}</p>
                    </div>

                    <!-- Excerpt -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Tóm tắt <span class="text-xs text-muted-foreground">(hiển thị trên danh sách)</span></Label>
                        <textarea v-model="form.excerpt" rows="2" placeholder="Mô tả ngắn về bài viết..."
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                    </div>

                    <!-- Content -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Nội dung <span class="text-red-500">*</span></Label>
                        <textarea v-model="form.content" rows="8" placeholder="Nội dung bài viết... Hỗ trợ **in đậm** và xuống dòng"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring font-mono" />
                        <p v-if="form.errors.content" class="text-xs text-red-500">{{ form.errors.content }}</p>
                        <p class="text-xs text-muted-foreground">Tip: **text** = in đậm, xuống dòng bằng Enter</p>
                    </div>

                    <!-- Tags -->
                    <div class="flex flex-col gap-1.5">
                        <Label>Tags <span class="text-xs text-muted-foreground">(phân cách bằng dấu phẩy)</span></Label>
                        <Input v-model="tagsText" placeholder="khuyến mãi, tính năng, cập nhật" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="closeDialog">Hủy</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editingPost ? 'Lưu thay đổi' : 'Đăng bài' }}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </Transition>
</template>
