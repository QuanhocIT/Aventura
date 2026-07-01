<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, Eye, Tag } from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import NewsCard from '@/components/NewsCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface NewsPost {
    id: number; title: string; slug: string; excerpt: string | null;
    content: string; category: string; tags: string[];
    featured_image_url: string | null; is_featured: boolean;
    view_count: number; published_at: string; author_name: string;
}

const props = defineProps<{
    post: NewsPost;
    relatedPosts: NewsPost[];
}>();

const categoryLabels: Record<string, string> = {
    'tin-tuc':    'Tin tức',
    'khuyen-mai': 'Khuyến mãi',
    'thanh-cong': 'Thành công',
    'cap-nhat':   'Cập nhật',
    'thong-bao':  'Thông báo',
};
const categoryColors: Record<string, string> = {
    'tin-tuc':    'bg-blue-100 text-blue-700',
    'khuyen-mai': 'bg-green-100 text-green-700',
    'thanh-cong': 'bg-purple-100 text-purple-700',
    'cap-nhat':   'bg-amber-100 text-amber-700',
    'thong-bao':  'bg-red-100 text-red-700',
};

const readingProgress = ref(0);

function onScroll() {
    const el = document.documentElement;
    const scrollable = el.scrollHeight - el.clientHeight;
    readingProgress.value = scrollable > 0 ? Math.min(100, (el.scrollTop / scrollable) * 100) : 0;
}

onMounted(() => window.addEventListener('scroll', onScroll, { passive: true }));
onUnmounted(() => window.removeEventListener('scroll', onScroll));

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Input is HTML-escaped first — post.content is author-entered rich text
// rendered via v-html, and news posts are publicly viewable.
function renderMarkdown(text: string): string {
    return escapeHtml(text)
        .replace(/^### (.+)$/gm, '<h3 class="text-lg font-bold mt-6 mb-2">$1</h3>')
        .replace(/^## (.+)$/gm, '<h2 class="text-xl font-bold mt-8 mb-3">$1</h2>')
        .replace(/^# (.+)$/gm, '<h1 class="text-2xl font-bold mt-8 mb-4">$1</h1>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/^- (.+)$/gm, '<li class="ml-4 list-disc">$1</li>')
        .replace(/(<li[^>]*>.*<\/li>\n?)+/g, '<ul class="my-3 space-y-1">$&</ul>')
        .replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" class="text-primary underline hover:no-underline" target="_blank" rel="noopener">$1</a>')
        .replace(/\n\n/g, '</p><p class="mb-4">')
        .replace(/\n/g, '<br>');
}
</script>

<template>
    <Head :title="`${post.title} - Aventura`">
        <meta property="og:title" :content="post.title" />
        <meta property="og:description" :content="post.excerpt ?? post.title" />
        <meta v-if="post.featured_image_url" property="og:image" :content="post.featured_image_url" />
        <meta property="og:type" content="article" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="post.title" />
        <meta name="twitter:description" :content="post.excerpt ?? post.title" />
        <meta name="description" :content="post.excerpt ?? post.title" />
        <component :is="'script'" type="application/ld+json" v-html="JSON.stringify({
            '@context': 'https://schema.org',
            '@type': 'Article',
            headline: post.title,
            description: post.excerpt,
            image: post.featured_image_url,
            author: { '@type': 'Person', name: post.author_name },
            datePublished: post.published_at,
            publisher: { '@type': 'Organization', name: 'Aventura' },
        })" />
    </Head>

    <!-- Reading progress bar -->
    <div
        class="fixed left-0 top-0 z-50 h-0.5 bg-primary transition-all duration-100"
        :style="`width: ${readingProgress}%`"
    />

    <!-- Featured image hero -->
    <div v-if="post.featured_image_url" class="relative h-64 w-full overflow-hidden sm:h-80 lg:h-96">
        <img :src="post.featured_image_url" :alt="post.title" class="h-full w-full object-cover" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
    </div>

    <div class="mx-auto max-w-3xl px-4 py-10 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="mb-6 flex items-center gap-2 text-sm text-muted-foreground">
            <Link href="/" class="hover:text-foreground transition">Trang chủ</Link>
            <span>/</span>
            <Link href="/tin-tuc" class="hover:text-foreground transition">Tin tức</Link>
            <span>/</span>
            <span class="line-clamp-1 text-foreground">{{ post.title }}</span>
        </nav>

        <!-- Category + tags -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                :class="categoryColors[post.category] ?? 'bg-slate-100 text-slate-700'"
            >
                {{ categoryLabels[post.category] ?? post.category }}
            </span>
            <span
                v-for="tag in post.tags"
                :key="tag"
                class="inline-flex items-center gap-1 rounded-full border border-border px-2.5 py-0.5 text-xs text-muted-foreground"
            >
                <Tag class="size-2.5" />{{ tag }}
            </span>
        </div>

        <!-- Title -->
        <h1 class="mb-4 text-2xl font-bold leading-snug text-foreground sm:text-3xl">
            {{ post.title }}
        </h1>

        <!-- Meta -->
        <div class="mb-8 flex flex-wrap items-center gap-4 border-b pb-6 text-sm text-muted-foreground">
            <span class="font-medium text-foreground">{{ post.author_name }}</span>
            <span v-if="post.published_at" class="flex items-center gap-1.5">
                <Calendar class="size-3.5" /> {{ post.published_at }}
            </span>
            <span class="flex items-center gap-1.5">
                <Eye class="size-3.5" /> {{ post.view_count }} lượt xem
            </span>
        </div>

        <!-- Excerpt -->
        <p v-if="post.excerpt" class="mb-6 rounded-xl border-l-4 border-primary bg-primary/5 px-4 py-3 text-base text-foreground/80 italic">
            {{ post.excerpt }}
        </p>

        <!-- Content -->
        <div
            class="prose prose-sm max-w-none text-foreground/90 leading-relaxed [&_strong]:text-foreground [&_p]:mb-4"
            v-html="'<p class=\'mb-4\'>' + renderMarkdown(post.content) + '</p>'"
        />

        <!-- Back link -->
        <div class="mt-12 border-t pt-8">
            <Link href="/tin-tuc" class="inline-flex items-center gap-2 text-sm text-primary hover:underline">
                <ArrowLeft class="size-4" /> Quay lại danh sách tin tức
            </Link>
        </div>
    </div>

    <!-- Related posts -->
    <div class="border-t bg-muted/30 py-12">
        <div class="mx-auto max-w-7xl px-4 lg:px-8">
            <h2 class="mb-6 text-lg font-semibold">Bài viết liên quan</h2>
            <div v-if="relatedPosts.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <NewsCard v-for="p in relatedPosts" :key="p.id" v-bind="p" />
            </div>
            <div v-else class="rounded-xl border border-border bg-muted/30 py-8 text-center">
                <p class="text-sm text-muted-foreground">Chưa có bài viết liên quan.</p>
            </div>
        </div>
    </div>
</template>
