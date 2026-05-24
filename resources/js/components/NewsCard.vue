<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Calendar, Eye } from 'lucide-vue-next';

interface NewsCardProps {
    title: string;
    slug: string;
    excerpt?: string | null;
    category: string;
    featured_image_url?: string | null;
    is_featured?: boolean;
    view_count?: number;
    published_at?: string;
    author_name?: string;
}

defineProps<NewsCardProps>();

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
</script>

<template>
    <Link
        :href="`/tin-tuc/${slug}`"
        class="group flex flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-sm transition hover:shadow-md hover:-translate-y-0.5"
    >
        <!-- Image -->
        <div class="relative aspect-video overflow-hidden bg-muted">
            <img
                v-if="featured_image_url"
                :src="featured_image_url"
                :alt="title"
                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
            />
            <div v-else class="flex h-full items-center justify-center text-muted-foreground/30">
                <svg class="size-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <!-- Featured badge -->
            <span
                v-if="is_featured"
                class="absolute left-3 top-3 rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-foreground"
            >Nổi bật</span>
        </div>

        <!-- Content -->
        <div class="flex flex-1 flex-col gap-2 p-4">
            <span
                class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-medium"
                :class="categoryColors[category] ?? 'bg-slate-100 text-slate-700'"
            >
                {{ categoryLabels[category] ?? category }}
            </span>

            <h3 class="line-clamp-2 text-sm font-semibold leading-snug text-foreground group-hover:text-primary transition-colors">
                {{ title }}
            </h3>

            <p v-if="excerpt" class="line-clamp-2 text-xs text-muted-foreground">
                {{ excerpt }}
            </p>

            <div class="mt-auto flex items-center gap-3 pt-2 text-xs text-muted-foreground">
                <span v-if="published_at" class="flex items-center gap-1">
                    <Calendar class="size-3" />
                    {{ published_at }}
                </span>
                <span v-if="view_count !== undefined" class="flex items-center gap-1">
                    <Eye class="size-3" />
                    {{ view_count }}
                </span>
            </div>
        </div>
    </Link>
</template>
