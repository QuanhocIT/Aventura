<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = withDefaults(
    defineProps<{
        links: PaginationLink[];
        /**
         * 'link'   — điều hướng bằng Inertia <Link> (mặc định, dùng cho trang thường).
         * 'button' — phát sự kiện @navigate để trang tự gọi router.get, dùng khi cần
         *            giữ nguyên tab/trạng thái đang mở (preserveState).
         */
        as?: 'link' | 'button';
        /** Dòng tóm tắt "Trang x / y · Tổng z dòng" — bỏ qua nếu không truyền. */
        currentPage?: number;
        lastPage?: number;
        total?: number;
        class?: HTMLAttributes['class'];
    }>(),
    { as: 'link' },
);

const emit = defineEmits<{ navigate: [url: string] }>();

// v-html của nhãn phải nằm trên thẻ NATIVE (<span>/<button>), không đặt trực tiếp
// lên <Link> — Vue không đảm bảo fallthrough của innerHTML xuống component con.
const itemClass = (link: PaginationLink) =>
    cn(
        'inline-flex items-center justify-center rounded-lg border px-3 py-1.5 text-xs font-medium transition-all duration-200 hover:scale-105 active:scale-95',
        link.active
            ? 'scale-105 border-primary bg-primary text-primary-foreground shadow-sm shadow-primary/20'
            : 'border-border/60 text-muted-foreground hover:bg-muted/60 hover:text-foreground',
        !link.url && 'pointer-events-none opacity-40',
    );

const showSummary = () =>
    props.currentPage !== undefined && props.lastPage !== undefined;
</script>

<template>
    <div
        v-if="links.length > 3"
        :class="
            cn(
                'flex flex-wrap items-center gap-2 border-t border-border/60 px-4 py-3',
                showSummary() ? 'justify-between' : 'justify-center',
                props.class,
            )
        "
    >
        <span
            v-if="showSummary()"
            class="text-[11px] font-medium text-muted-foreground"
        >
            Trang {{ currentPage }} / {{ lastPage
            }}<template v-if="total !== undefined">
                · Tổng {{ total.toLocaleString('vi-VN') }} dòng</template
            >
        </span>

        <div class="flex flex-wrap gap-1">
            <template v-for="link in links" :key="link.label">
                <button
                    v-if="as === 'button'"
                    type="button"
                    :disabled="!link.url"
                    :class="itemClass(link)"
                    @click="link.url && emit('navigate', link.url)"
                >
                    <span v-html="link.label" />
                </button>
                <Link
                    v-else
                    :href="link.url ?? '#'"
                    preserve-state
                    :class="itemClass(link)"
                >
                    <span v-html="link.label" />
                </Link>
            </template>
        </div>
    </div>
</template>
