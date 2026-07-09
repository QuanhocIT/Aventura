<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';
import NewsCard from '@/components/NewsCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface NewsPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    category: string;
    featured_image_url: string | null;
    is_featured: boolean;
    published_at: string;
}

const props = defineProps<{
    latestNews?: NewsPost[];
}>();

const latestNewsList = computed(() => props.latestNews ?? []);
</script>

<template>
    <!-- ── Tin tức mới nhất ──────────────────────────────────────── -->
    <section
        v-if="latestNewsList.length > 0"
        id="news"
        class="px-4 py-10 lg:px-8 lg:py-12"
    >
        <div class="mx-auto max-w-7xl">
            <div class="reveal-on-scroll flex items-end justify-between">
                <div class="max-w-2xl">
                    <Badge
                        variant="outline"
                        class="mb-3 border-primary/30 bg-primary/5 text-primary"
                        >Tin tức</Badge
                    >
                    <h2 class="text-3xl font-semibold">Tin tức & Cập nhật</h2>
                    <p class="mt-3 text-muted-foreground">
                        Những bài viết mới nhất về Aventura, nhà hàng và ngành
                        dịch vụ ăn uống.
                    </p>
                </div>
                <Link
                    href="/tin-tuc"
                    class="hidden shrink-0 items-center gap-1 text-sm font-medium text-primary hover:underline sm:flex"
                >
                    Xem tất cả <ChevronRight class="size-4" />
                </Link>
            </div>
            <div
                class="reveal-on-scroll mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"
            >
                <NewsCard
                    v-for="post in latestNewsList"
                    :key="post.id"
                    :title="post.title"
                    :slug="post.slug"
                    :excerpt="post.excerpt"
                    :category="post.category"
                    :featured_image_url="post.featured_image_url"
                    :is_featured="post.is_featured"
                    :published_at="post.published_at"
                />
            </div>
            <div class="mt-6 flex justify-center sm:hidden">
                <Button as-child variant="outline">
                    <Link href="/tin-tuc">Xem tất cả tin tức</Link>
                </Button>
            </div>
        </div>
    </section>
</template>
