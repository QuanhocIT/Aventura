<script setup lang="ts">
import {
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronsUpDown,
    ChevronUp,
    Inbox,
    Search,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';

/**
 * Bảng dữ liệu dùng chung: sort client-side, tìm kiếm, phân trang,
 * skeleton khi loading, empty-state. Tùy biến ô qua slot #cell-<key>,
 * cột thao tác qua slot #actions.
 *
 * <DataTable :columns="cols" :rows="rows" :search-keys="['name']" :per-page="10">
 *     <template #cell-status="{ row }"><Badge>{{ row.status }}</Badge></template>
 *     <template #actions="{ row }"><Button @click="edit(row)">Sửa</Button></template>
 * </DataTable>
 */

export interface DataTableColumn {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'center' | 'right';
    /** class bổ sung cho ô (vd: 'font-mono', 'w-32') */
    class?: string;
}

const props = withDefaults(
    defineProps<{
        columns: DataTableColumn[];
        rows: Record<string, any>[];
        loading?: boolean;
        /** các field dùng cho ô tìm kiếm; bỏ trống = ẩn ô tìm kiếm */
        searchKeys?: string[];
        /** số dòng mỗi trang; 0 = không phân trang */
        perPage?: number;
        emptyText?: string;
        rowKey?: string;
        /** có cột thao tác (slot #actions) hay không */
        hasActions?: boolean;
    }>(),
    {
        loading: false,
        searchKeys: () => [],
        perPage: 10,
        emptyText: 'Chưa có dữ liệu.',
        rowKey: 'id',
        hasActions: false,
    },
);

const search = ref('');
const sortKey = ref<string | null>(null);
const sortDir = ref<'asc' | 'desc'>('asc');
const page = ref(1);

watch([search, () => props.rows.length], () => {
    page.value = 1;
});

const filtered = computed(() => {
    if (!search.value.trim() || props.searchKeys.length === 0) {
        return props.rows;
    }

    const q = search.value.trim().toLowerCase();

    return props.rows.filter((row) =>
        props.searchKeys.some((key) =>
            String(row[key] ?? '')
                .toLowerCase()
                .includes(q),
        ),
    );
});

const sorted = computed(() => {
    if (!sortKey.value) {
        return filtered.value;
    }

    const key = sortKey.value;
    const dir = sortDir.value === 'asc' ? 1 : -1;

    return [...filtered.value].sort((a, b) => {
        const va = a[key];
        const vb = b[key];

        if (va == null && vb == null) {
            return 0;
        }

        if (va == null) {
            return dir;
        }

        if (vb == null) {
            return -dir;
        }

        if (typeof va === 'number' && typeof vb === 'number') {
            return (va - vb) * dir;
        }

        return (
            String(va).localeCompare(String(vb), 'vi', { numeric: true }) * dir
        );
    });
});

const totalPages = computed(() =>
    props.perPage > 0
        ? Math.max(1, Math.ceil(sorted.value.length / props.perPage))
        : 1,
);

const paged = computed(() => {
    if (props.perPage <= 0) {
        return sorted.value;
    }

    const start = (page.value - 1) * props.perPage;

    return sorted.value.slice(start, start + props.perPage);
});

const rangeLabel = computed(() => {
    if (sorted.value.length === 0) {
        return '';
    }

    const start = props.perPage > 0 ? (page.value - 1) * props.perPage + 1 : 1;
    const end =
        props.perPage > 0
            ? Math.min(page.value * props.perPage, sorted.value.length)
            : sorted.value.length;

    return `${start}–${end} / ${sorted.value.length}`;
});

function toggleSort(col: DataTableColumn) {
    if (!col.sortable) {
        return;
    }

    if (sortKey.value === col.key) {
        if (sortDir.value === 'asc') {
            sortDir.value = 'desc';
        } else {
            sortKey.value = null;
            sortDir.value = 'asc';
        }
    } else {
        sortKey.value = col.key;
        sortDir.value = 'asc';
    }
}

const alignClass = (align?: string) =>
    align === 'right'
        ? 'text-right'
        : align === 'center'
          ? 'text-center'
          : 'text-left';
</script>

<template>
    <div class="space-y-3">
        <!-- Thanh tìm kiếm -->
        <div v-if="searchKeys.length > 0" class="relative max-w-xs">
            <Search
                class="absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="search"
                placeholder="Tìm kiếm..."
                class="h-9 pl-9 text-xs"
            />
        </div>

        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full text-xs">
                <thead
                    class="bg-neutral-50 text-neutral-500 dark:bg-neutral-900 dark:text-neutral-400"
                >
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            class="px-3 py-2.5 font-semibold whitespace-nowrap select-none"
                            :class="[
                                alignClass(col.align),
                                col.sortable
                                    ? 'cursor-pointer transition-colors hover:text-foreground'
                                    : '',
                            ]"
                            @click="toggleSort(col)"
                        >
                            <span class="inline-flex items-center gap-1">
                                {{ col.label }}
                                <template v-if="col.sortable">
                                    <ChevronUp
                                        v-if="
                                            sortKey === col.key &&
                                            sortDir === 'asc'
                                        "
                                        class="h-3 w-3"
                                    />
                                    <ChevronDown
                                        v-else-if="
                                            sortKey === col.key &&
                                            sortDir === 'desc'
                                        "
                                        class="h-3 w-3"
                                    />
                                    <ChevronsUpDown
                                        v-else
                                        class="h-3 w-3 opacity-40"
                                    />
                                </template>
                            </span>
                        </th>
                        <th
                            v-if="hasActions"
                            class="w-1 px-3 py-2.5 text-right font-semibold"
                        >
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Skeleton loading -->
                    <template v-if="loading">
                        <tr
                            v-for="i in 5"
                            :key="`sk-${i}`"
                            class="border-t border-border"
                        >
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="px-3 py-3"
                            >
                                <Skeleton class="h-3.5 w-full max-w-32" />
                            </td>
                            <td v-if="hasActions" class="px-3 py-3">
                                <Skeleton class="ml-auto h-3.5 w-12" />
                            </td>
                        </tr>
                    </template>

                    <!-- Empty state -->
                    <tr v-else-if="paged.length === 0">
                        <td
                            :colspan="columns.length + (hasActions ? 1 : 0)"
                            class="px-3 py-10"
                        >
                            <slot name="empty">
                                <div
                                    class="flex flex-col items-center gap-2 text-center"
                                >
                                    <div
                                        class="rounded-full bg-neutral-100 p-3 dark:bg-neutral-800"
                                    >
                                        <Inbox
                                            class="h-5 w-5 text-neutral-400"
                                        />
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            search
                                                ? `Không tìm thấy kết quả cho "${search}"`
                                                : emptyText
                                        }}
                                    </p>
                                </div>
                            </slot>
                        </td>
                    </tr>

                    <!-- Dữ liệu -->
                    <tr
                        v-for="row in paged"
                        v-else
                        :key="row[rowKey]"
                        class="border-t border-border transition-colors hover:bg-accent/50"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            class="px-3 py-2.5"
                            :class="[alignClass(col.align), col.class]"
                        >
                            <slot
                                :name="`cell-${col.key}`"
                                :row="row"
                                :value="row[col.key]"
                            >
                                {{ row[col.key] ?? '—' }}
                            </slot>
                        </td>
                        <td
                            v-if="hasActions"
                            class="px-3 py-2.5 text-right whitespace-nowrap"
                        >
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Phân trang -->
        <div
            v-if="perPage > 0 && sorted.length > perPage"
            class="flex items-center justify-between"
        >
            <p class="text-[11px] text-muted-foreground">
                Hiển thị {{ rangeLabel }}
            </p>
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-7 w-7 p-0"
                    :disabled="page <= 1"
                    @click="page--"
                >
                    <ChevronLeft class="h-3.5 w-3.5" />
                </Button>
                <span class="px-2 text-[11px] font-semibold tabular-nums"
                    >{{ page }} / {{ totalPages }}</span
                >
                <Button
                    variant="outline"
                    size="sm"
                    class="h-7 w-7 p-0"
                    :disabled="page >= totalPages"
                    @click="page++"
                >
                    <ChevronRight class="h-3.5 w-3.5" />
                </Button>
            </div>
        </div>
    </div>
</template>
