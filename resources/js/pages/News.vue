<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Flame, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import NewsCard from '@/components/NewsCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface NewsPost {
    id: number; title: string; slug: string; excerpt: string | null;
    category: string; featured_image_url: string | null;
    is_featured: boolean; view_count: number; published_at: string;
    author_name: string;
}
interface Paginated {
    data: NewsPost[];
    current_page: number; last_page: number; total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

const props = defineProps<{
    posts: Paginated;
    featuredPosts: NewsPost[];
    activeCategory: string | null;
    activeSearch?: string | null;
    categoryCounts?: Record<string, number>;
}>();

function paginationLabel(label: string): string {
    if (label === '&laquo; Previous') {
return '←';
}

    if (label === 'Next &raquo;') {
return '→';
}

    return label;
}

const searchQuery = ref(props.activeSearch ?? '');

const categoryTabs = [
    { value: '',           label: 'Tất cả' },
    { value: 'tin-tuc',    label: 'Tin tức' },
    { value: 'khuyen-mai', label: 'Khuyến mãi' },
    { value: 'thanh-cong', label: 'Thành công' },
    { value: 'cap-nhat',   label: 'Cập nhật' },
    { value: 'thong-bao',  label: 'Thông báo' },
];

function filterBy(val: string) {
    const params: Record<string, string> = {};

    if (val) {
params.category = val;
}

    if (searchQuery.value) {
params.search = searchQuery.value;
}

    router.get('/tin-tuc', params, { preserveState: true, replace: true });
}

function doSearch() {
    const params: Record<string, string> = {};

    if (searchQuery.value) {
params.search = searchQuery.value;
}

    if (props.activeCategory) {
params.category = props.activeCategory;
}

    router.get('/tin-tuc', params, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Tin tức &amp; Cập nhật - Aventura" />

    <!-- Hero -->
    <section class="bg-gradient-to-br from-primary/10 via-primary/5 to-background px-4 py-16 text-center">
        <div class="mx-auto max-w-2xl">
            <span class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                <Flame class="size-3.5" /> Tin tức mới nhất
            </span>
            <h1 class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
                Tin tức & Cập nhật
            </h1>
            <p class="mt-3 text-muted-foreground">
                Khuyến mãi, cập nhật hệ thống và câu chuyện thành công từ cộng đồng Aventura.
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-7xl px-4 py-12 lg:px-8">

        <!-- Featured hero article -->
        <div v-if="featuredPosts.length && !activeCategory && !activeSearch" class="mb-12">
            <div class="relative overflow-hidden rounded-2xl bg-black">
                <img
                    v-if="featuredPosts[0].featured_image_url"
                    :src="featuredPosts[0].featured_image_url"
                    :alt="featuredPosts[0].title"
                    class="h-[360px] w-full object-cover opacity-60 transition-transform duration-700 hover:scale-105"
                />
                <div v-else class="h-[360px] w-full bg-gradient-to-br from-primary/40 to-muted/30" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />
                <div class="absolute bottom-0 left-0 p-6 sm:p-8">
                    <span class="mb-3 inline-block rounded-full bg-primary px-3 py-1 text-xs font-semibold text-primary-foreground">
                        {{ featuredPosts[0].category }}
                    </span>
                    <h2 class="max-w-2xl text-2xl font-bold leading-snug text-white sm:text-3xl">
                        {{ featuredPosts[0].title }}
                    </h2>
                    <p v-if="featuredPosts[0].excerpt" class="mt-2 max-w-xl text-sm text-white/70 line-clamp-2">
                        {{ featuredPosts[0].excerpt }}
                    </p>
                    <Link
                        :href="`/tin-tuc/${featuredPosts[0].slug}`"
                        class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-white/90 underline-offset-2 hover:underline"
                    >
                        Đọc bài viết →
                    </Link>
                </div>
            </div>
        </div>

        <!-- Search bar -->
        <div class="mb-6 flex gap-2">
            <Input
                v-model="searchQuery"
                placeholder="Tìm kiếm bài viết..."
                class="max-w-md"
                @keyup.enter="doSearch"
            />
            <Button variant="outline" @click="doSearch">
                <Search class="size-4" />
            </Button>
        </div>

        <!-- Category filter tabs -->
        <div class="mb-8 flex flex-wrap gap-2">
            <button
                v-for="tab in categoryTabs"
                :key="tab.value"
                @click="filterBy(tab.value)"
                :class="(activeCategory ?? '') === tab.value
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'"
                class="rounded-full px-4 py-1.5 text-sm font-medium transition"
            >
                {{ tab.label }}
                <span v-if="tab.value && categoryCounts?.[tab.value]" class="ml-1 opacity-70">
                    ({{ categoryCounts[tab.value] }})
                </span>
            </button>
        </div>

        <!-- Posts grid -->
        <div v-if="posts.data.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <NewsCard v-for="post in posts.data" :key="post.id" v-bind="post" />
        </div>

        <div v-else class="py-20 text-center text-muted-foreground">
            <p class="text-lg">Chưa có bài viết nào trong danh mục này.</p>
            <button @click="filterBy('')" class="mt-3 text-sm text-primary underline-offset-2 hover:underline">
                Xem tất cả bài viết
            </button>
        </div>

        <!-- Pagination (safe — no v-html) -->
        <div v-if="posts.last_page > 1" class="mt-10 flex justify-center gap-1">
            <template v-for="link in posts.links" :key="link.label">
                <button
                    v-if="link.url"
                    @click="router.get(link.url)"
                    :class="link.active
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-foreground hover:bg-muted/80'"
                    class="min-w-9 rounded-lg px-3 py-2 text-sm font-medium transition"
                >
                    {{ paginationLabel(link.label) }}
                </button>
            </template>
        </div>
    </div>
</template>
