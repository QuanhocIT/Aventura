<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Database, 
    HardDrive, 
    Trash2, 
    FileQuestion, 
    ChevronLeft, 
    ChevronRight,
    RefreshCw,
    Search,
    AlertCircle
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, TerminalCard, StatCard, LedIndicator, EmptyState } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface OrphanFile {
    id: number;
    file_name: string;
    file_path: string;
    file_url: string;
    disk: string;
    media_type: string;
    mime_type: string;
    size_bytes: number;
    size_mb: number;
    attachable_type: string | null;
    attachable_id: number | null;
    restaurant_name: string;
    restaurant_code: string | null;
    created_at: string;
}

const props = defineProps<{
    orphans: {
        data: OrphanFile[];
        current_page: number;
        last_page: number;
        total: number;
    };
    stats: {
        total_count: number;
        total_mb: number;
        default_disk: string;
    };
}>();

const selectedIds = ref<number[]>([]);
const processing = ref(false);

const isAllSelected = computed(() => {
    return props.orphans.data.length > 0 && props.orphans.data.every(o => selectedIds.value.includes(o.id));
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        // Deselect all on current page
        const currentPageIds = props.orphans.data.map(o => o.id);
        selectedIds.value = selectedIds.value.filter(id => !currentPageIds.includes(id));
    } else {
        // Select all on current page
        props.orphans.data.forEach(o => {
            if (!selectedIds.value.includes(o.id)) {
                selectedIds.value.push(o.id);
            }
        });
    }
}

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(i => i !== id);
    } else {
        selectedIds.value.push(id);
    }
}

function cleanupSelected() {
    if (selectedIds.value.length === 0) {
return;
}
    
    if (confirm(`Bạn có chắc chắn muốn xóa vĩnh viễn ${selectedIds.value.length} tệp đã chọn để giải phóng bộ nhớ? Thao tác này không thể hoàn tác.`)) {
        processing.value = true;
        router.post('/super-admin/garbage-collector/cleanup', {
            ids: selectedIds.value
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                processing.value = false;
            },
            onError: () => {
                processing.value = false;
            }
        });
    }
}

function cleanupAll() {
    if (confirm('CẢNH BÁO: Bạn có chắc chắn muốn xóa TOÀN BỘ tệp mồ côi trong hệ thống? Thao tác này sẽ xóa vĩnh viễn tất cả tệp không còn liên kết để giải phóng dung lượng.')) {
        processing.value = true;
        router.post('/super-admin/garbage-collector/cleanup', {
            all: true
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
                processing.value = false;
            },
            onError: () => {
                processing.value = false;
            }
        });
    }
}

function deleteSingle(item: OrphanFile) {
    if (confirm(`Xóa vĩnh viễn tệp "${item.file_name}"?`)) {
        processing.value = true;
        router.post('/super-admin/garbage-collector/cleanup', {
            ids: [item.id]
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = selectedIds.value.filter(id => id !== item.id);
                processing.value = false;
            },
            onError: () => {
                processing.value = false;
            }
        });
    }
}

function navigatePage(page: number) {
    router.get('/super-admin/garbage-collector', { page }, { preserveState: true });
}

function getAttachableLabel(type: string | null) {
    if (!type) {
return 'Không có liên kết';
}

    const parts = type.split('\\');

    return parts[parts.length - 1];
}
</script>

<template>
    <Head title="Dọn dẹp tài nguyên & Rác" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Dọn dẹp tài nguyên & Hạn ngạch"
            subtitle="Quét và xóa bỏ các tệp mồ côi không còn liên kết trong CSDL để tối ưu dung lượng."
            :icon="Trash2"
        >
            <template #actions>
                <Button
                    variant="outline"
                    size="sm"
                    class="gap-1.5 cursor-pointer"
                    @click="router.reload({ preserveScroll: true })"
                    :disabled="processing"
                >
                    <RefreshCw class="size-3.5" :class="{ 'animate-spin': processing }" /> Quét lại
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    class="gap-1.5 cursor-pointer bg-rose-600 hover:bg-rose-700 shadow-md rounded-xl"
                    @click="cleanupAll"
                    :disabled="stats.total_count === 0 || processing"
                >
                    <Trash2 class="size-3.5" /> Dọn sạch toàn bộ ({{ stats.total_count }} tệp)
                </Button>
            </template>
        </PageHeader>

        <!-- Stats Overview Widgets -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card class="relative overflow-hidden border-border bg-card/60 backdrop-blur-md shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Tổng số tệp mồ côi</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-foreground font-mono">{{ stats.total_count }}</span>
                        <span class="text-xs text-muted-foreground">tệp cần dọn dẹp</span>
                    </div>
                    <Database class="absolute right-4 bottom-4 size-12 text-slate-200 dark:text-slate-800/40 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border-border bg-card/60 backdrop-blur-md shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Dung lượng lãng phí</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-rose-600 dark:text-rose-450 font-mono">{{ stats.total_mb }}</span>
                        <span class="text-xs text-muted-foreground">MB bộ nhớ</span>
                    </div>
                    <HardDrive class="absolute right-4 bottom-4 size-12 text-rose-200/50 dark:text-rose-950/20 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border-border bg-card/60 backdrop-blur-md shadow-xs">
                <CardHeader class="pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Storage Driver mặc định</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-foreground uppercase font-mono">{{ stats.default_disk }}</span>
                    </div>
                    <span class="text-xs text-muted-foreground">Configured in filesystems.php</span>
                </CardContent>
            </Card>
        </div>

        <!-- Warning Alert Banner -->
        <div v-if="stats.total_count > 0" class="flex items-start gap-3 rounded-2xl border border-amber-200/60 bg-amber-500/10 p-4 text-amber-800 dark:text-amber-300">
            <AlertCircle class="size-5 text-amber-500 shrink-0 mt-0.5" />
            <div>
                <h4 class="font-bold text-sm">Cảnh báo bảo tồn dữ liệu</h4>
                <p class="text-xs mt-0.5">
                    Các tệp mồ côi dưới đây là tệp tin đã tải lên hệ thống nhưng không còn liên kết với sản phẩm, bài viết, hoặc tài khoản người dùng nào (thường do thao tác xóa món ăn hoặc cập nhật hình ảnh lỗi). Khi nhấn xóa vĩnh viễn, tệp vật lý tương ứng trên ổ đĩa / S3 Cloud sẽ được gỡ bỏ hoàn toàn.
                </p>
            </div>
        </div>

        <!-- Clean up Actions Bar -->
        <div v-if="selectedIds.length > 0" class="flex items-center justify-between bg-muted/50 dark:bg-zinc-900 border border-border/80 px-4 py-3 rounded-2xl shadow-xs">
            <span class="text-sm font-medium text-foreground">
                Đã chọn <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ selectedIds.length }}</span> tệp mồ côi
            </span>
            <Button 
                variant="destructive" 
                size="sm"
                class="gap-1.5 cursor-pointer bg-rose-600 hover:bg-rose-700 shadow-md"
                @click="cleanupSelected"
                :disabled="processing"
            >
                <Trash2 class="size-3.5" /> Xóa các mục đã chọn
            </Button>
        </div>

        <!-- Orphans Table -->
        <Card class="border-border bg-card shadow-xs">
            <CardHeader class="pb-0">
                <CardTitle class="text-base">Danh sách tệp mồ côi</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="orphans.data.length === 0" class="flex flex-col items-center justify-center py-20 text-muted-foreground/60">
                    <div class="rounded-full bg-muted p-4 mb-3">
                        <Database class="h-10 w-10 text-emerald-500" />
                    </div>
                    <h3 class="font-bold text-foreground">Hệ thống sạch sẽ!</h3>
                    <p class="text-sm text-center max-w-xs mt-1">Không tìm thấy tệp mồ côi nào trong hệ thống. Tuyệt vời!</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40 text-muted-foreground font-semibold text-xs uppercase">
                                <th class="py-4 px-6 w-12 text-center">
                                    <input 
                                        type="checkbox" 
                                        :checked="isAllSelected" 
                                        @change="toggleSelectAll" 
                                        class="size-4 rounded accent-primary border-border cursor-pointer"
                                    />
                                </th>
                                <th class="py-4 px-4">Xem trước & Tên tệp</th>
                                <th class="py-4 px-4">Nhà hàng (Tenant)</th>
                                <th class="py-4 px-4">Loại thực thể</th>
                                <th class="py-4 px-4">Kích thước</th>
                                <th class="py-4 px-4">Ngày tải lên</th>
                                <th class="py-4 px-6 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr 
                                v-for="item in orphans.data" 
                                :key="item.id" 
                                class="hover:bg-muted/30 transition-colors group"
                            >
                                <td class="py-4 px-6 text-center">
                                    <input 
                                        type="checkbox" 
                                        :checked="selectedIds.includes(item.id)" 
                                        @change="toggleSelect(item.id)" 
                                        class="size-4 rounded accent-primary border-border cursor-pointer"
                                    />
                                </td>
                                <td class="py-4 px-4 flex items-center gap-3">
                                    <div class="h-10 w-14 shrink-0 rounded-lg overflow-hidden border border-border/80 bg-muted flex items-center justify-center">
                                        <img 
                                            v-if="item.media_type === 'image'" 
                                            :src="item.file_url" 
                                            class="h-full w-full object-cover" 
                                            :alt="item.file_name"
                                            @error="(e: any) => e.target.style.display = 'none'"
                                        />
                                        <FileQuestion v-else class="size-5 text-muted-foreground" />
                                    </div>
                                    <div class="min-w-0 max-w-xs md:max-w-md">
                                        <p class="font-semibold text-foreground truncate" :title="item.file_name">
                                            {{ item.file_name }}
                                        </p>
                                        <p class="text-xs text-muted-foreground font-mono truncate" :title="item.file_path">
                                            {{ item.disk }} · {{ item.file_path }}
                                        </p>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <span v-if="item.restaurant_code" class="inline-flex flex-col">
                                        <span class="font-medium text-foreground">{{ item.restaurant_name }}</span>
                                        <span class="text-xs text-muted-foreground font-mono">{{ item.restaurant_code }}</span>
                                    </span>
                                    <span v-else class="text-muted-foreground">Hệ thống (Global)</span>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-slate-800/80 border px-2 py-0.5 text-xs text-slate-700 dark:text-slate-300">
                                        {{ getAttachableLabel(item.attachable_type) }}
                                        <span v-if="item.attachable_id" class="ml-1 font-mono">#{{ item.attachable_id }}</span>
                                    </span>
                                </td>
                                <td class="py-4 px-4 font-mono font-medium text-foreground">
                                    {{ item.size_mb }} MB
                                </td>
                                <td class="py-4 px-4 text-muted-foreground text-xs">
                                    {{ item.created_at }}
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <button 
                                        @click="deleteSingle(item)" 
                                        class="p-2 text-rose-500 rounded-lg hover:bg-rose-500/10 transition-colors opacity-0 group-hover:opacity-100 cursor-pointer"
                                        title="Xóa vĩnh viễn tệp này"
                                    >
                                        <Trash2 class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="orphans.last_page > 1" class="flex items-center justify-between border-t border-border/80 px-6 py-4">
                    <span class="text-xs text-muted-foreground">
                        Trang {{ orphans.current_page }} / {{ orphans.last_page }} · Tổng {{ orphans.total }} tệp mồ côi
                    </span>
                    <div class="flex items-center gap-2">
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="orphans.current_page <= 1"
                            @click="navigatePage(orphans.current_page - 1)"
                            class="cursor-pointer"
                        >
                            <ChevronLeft class="size-4 mr-1" /> Trước
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm" 
                            :disabled="orphans.current_page >= orphans.last_page"
                            @click="navigatePage(orphans.current_page + 1)"
                            class="cursor-pointer"
                        >
                            Sau <ChevronRight class="size-4 ml-1" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
