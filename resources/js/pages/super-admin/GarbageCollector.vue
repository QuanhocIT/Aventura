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
    AlertCircle,
    CheckCircle2,
    AlertTriangle,
    FileImage,
    FileText
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { PageHeader, TerminalCard, StatCard, LedIndicator, EmptyState } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { confirmDialog } from '@/composables/useConfirm';
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

async function cleanupSelected() {
    if (selectedIds.value.length === 0) {
return;
}
    
    if ((await confirmDialog({ title: 'Xác nhận thao tác', description: `Bạn có chắc chắn muốn xóa vĩnh viễn ${selectedIds.value.length} tệp đã chọn để giải phóng bộ nhớ? Thao tác này không thể hoàn tác.` }))) {
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

async function cleanupAll() {
    if ((await confirmDialog({ title: 'Xác nhận thao tác', description: 'CẢNH BÁO: Bạn có chắc chắn muốn xóa TOÀN BỘ tệp mồ côi trong hệ thống? Thao tác này sẽ xóa vĩnh viễn tất cả tệp không còn liên kết để giải phóng dung lượng.' }))) {
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

async function deleteSingle(item: OrphanFile) {
    if ((await confirmDialog({ title: 'Xác nhận thao tác', description: `Xóa vĩnh viễn tệp "${item.file_name}"?` }))) {
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

const storageAdvice = computed(() => {
    if (props.stats.total_count === 0) {
        return { status: 'perfect', label: 'Tối ưu', color: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20', desc: 'Không phát hiện bất kỳ tệp mồ côi nào trên hệ thống lưu trữ.' };
    }

    if (props.stats.total_mb > 50) {
        return { status: 'warning', label: 'Cần giải phóng', color: 'text-rose-500 bg-rose-500/10 border-rose-500/20', desc: `Phát hiện ${props.stats.total_count} tệp mồ côi chiếm ${props.stats.total_mb} MB dung lượng lãng phí.` };
    }

    return { status: 'caution', label: 'Khá tốt', color: 'text-amber-500 bg-amber-500/10 border-amber-500/20', desc: `Có ${props.stats.total_count} tệp mồ côi chiếm ít dung lượng (${props.stats.total_mb} MB).` };
});

const imageFilesCount = computed(() => {
    return props.orphans.data.filter(o => o.media_type === 'image').length;
});

const otherFilesCount = computed(() => {
    return props.orphans.data.length - imageFilesCount.value;
});

const imageRatio = computed(() => {
    if (!props.orphans.data.length) {
return 0;
}

    return Math.round((imageFilesCount.value / props.orphans.data.length) * 100);
});

const diskRecommendation = computed(() => {
    if (props.stats.default_disk === 'local') {
        return { status: 'caution', text: 'Storage đang lưu cục bộ. Hãy cân nhắc cấu hình Driver S3 trong production để tăng tính ổn định.' };
    }

    return { status: 'ok', text: `Đang lưu trên bộ lưu trữ đám mây (${props.stats.default_disk.toUpperCase()}).` };
});

const fileCleanupRecommendation = computed(() => {
    if (props.stats.total_count > 0) {
        return { status: 'warning', text: 'Nên xóa định kỳ các tệp mồ côi để tránh làm đầy các bản sao lưu database & file hệ thống.' };
    }

    return { status: 'ok', text: 'Hệ thống lưu trữ sạch sẽ, không cần can thiệp dọn dẹp.' };
});
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
                    class="gap-1.5 rounded-xl border-border hover:bg-muted font-bold text-xs h-9 cursor-pointer"
                    @click="router.reload({ preserveScroll: true })"
                    :disabled="processing"
                >
                    <RefreshCw class="size-3.5" :class="{ 'animate-spin': processing }" /> Quét lại
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    class="gap-1.5 cursor-pointer bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 text-white shadow-xs rounded-xl font-bold text-xs h-9"
                    @click="cleanupAll"
                    :disabled="stats.total_count === 0 || processing"
                >
                    <Trash2 class="size-3.5" /> Dọn sạch toàn bộ ({{ stats.total_count }} tệp)
                </Button>
            </template>
        </PageHeader>

        <!-- Stats Overview Widgets -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardHeader class="pb-2">
                    <CardTitle class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Tổng số tệp mồ côi</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-slate-800 dark:text-slate-200 font-mono">{{ stats.total_count }}</span>
                        <span class="text-xs text-muted-foreground font-semibold">tệp cần dọn dẹp</span>
                    </div>
                    <Database class="absolute right-4 bottom-4 size-12 text-orange-500/10 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardHeader class="pb-2">
                    <CardTitle class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Dung lượng lãng phí</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold text-rose-500 font-mono">{{ stats.total_mb }}</span>
                        <span class="text-xs text-muted-foreground font-semibold">MB bộ nhớ</span>
                    </div>
                    <HardDrive class="absolute right-4 bottom-4 size-12 text-rose-500/10 pointer-events-none" />
                </CardContent>
            </Card>

            <Card class="relative overflow-hidden border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs rounded-2xl">
                <CardHeader class="pb-2">
                    <CardTitle class="text-[10px] font-black text-muted-foreground uppercase tracking-wider">Storage Driver mặc định</CardTitle>
                </CardHeader>
                <CardContent class="pb-6">
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-800 dark:text-slate-200 uppercase font-mono">{{ stats.default_disk }}</span>
                    </div>
                    <span class="text-[10px] text-muted-foreground font-semibold">Cấu hình từ filesystems.php</span>
                </CardContent>
            </Card>
        </div>

        <!-- Warning Alert Banner -->
        <div v-if="stats.total_count > 0" class="flex items-start gap-3 rounded-2xl border border-amber-500/20 bg-amber-500/[0.04] p-4 text-amber-800 dark:text-amber-300 backdrop-blur-md">
            <AlertCircle class="size-5 text-amber-500 shrink-0 mt-0.5" />
            <div>
                <h4 class="font-bold text-xs uppercase tracking-wider">Cảnh báo bảo tồn dữ liệu</h4>
                <p class="text-xs mt-1 font-medium leading-relaxed">
                    Các tệp mồ côi dưới đây là tệp tin đã tải lên hệ thống nhưng không còn liên kết với sản phẩm, món ăn, bài viết hoặc tài khoản nào (thường do thao tác xóa món ăn hoặc cập nhật hình ảnh lỗi). Khi nhấn xóa vĩnh viễn, tệp vật lý tương ứng trên ổ đĩa / S3 Cloud sẽ được gỡ bỏ hoàn toàn.
                </p>
            </div>
        </div>

        <!-- Clean up Actions Bar -->
        <div v-if="selectedIds.length > 0" class="flex items-center justify-between border border-orange-500/20 bg-orange-500/5 px-4 py-3 rounded-2xl shadow-xs backdrop-blur-md">
            <span class="text-xs font-bold text-slate-850 dark:text-slate-100">
                Đã chọn <span class="font-mono font-black text-orange-500">{{ selectedIds.length }}</span> tệp mồ côi
            </span>
            <Button 
                variant="destructive" 
                size="sm"
                class="gap-1.5 cursor-pointer bg-rose-600 hover:bg-rose-700 shadow-md font-bold text-xs rounded-xl h-9"
                @click="cleanupSelected"
                :disabled="processing"
            >
                <Trash2 class="size-3.5" /> Xóa các mục đã chọn
            </Button>
        </div>

        <!-- Workspace Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left Column: Orphans Table (8 columns) -->
            <div class="lg:col-span-8 flex flex-col gap-6">
                <!-- Orphans Table Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardHeader class="border-b border-border/40 bg-muted/10 p-5">
                        <CardTitle class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider">Danh sách tệp mồ côi</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="orphans.data.length === 0" class="flex flex-col items-center justify-center py-20 text-muted-foreground/60">
                            <div class="rounded-full bg-muted p-4 mb-3">
                                <Database class="h-10 w-10 text-emerald-500" />
                            </div>
                            <h3 class="font-bold text-foreground text-xs uppercase tracking-wider">Hệ thống sạch sẽ!</h3>
                            <p class="text-[10px] text-center max-w-xs mt-1 font-semibold text-muted-foreground">Không tìm thấy tệp mồ côi nào trong hệ thống. Tuyệt vời!</p>
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-xs">
                                <thead>
                                    <tr class="border-b border-border/45 bg-muted/20 text-muted-foreground font-bold text-[10px] uppercase tracking-wider">
                                        <th class="py-3.5 px-5 w-12 text-center">
                                            <input 
                                                type="checkbox" 
                                                :checked="isAllSelected" 
                                                @change="toggleSelectAll" 
                                                class="size-4 rounded accent-orange-500 border-border cursor-pointer"
                                            />
                                        </th>
                                        <th class="py-3.5 px-4">Xem trước & Tên tệp</th>
                                        <th class="py-3.5 px-4">Nhà hàng (Tenant)</th>
                                        <th class="py-3.5 px-4">Loại thực thể</th>
                                        <th class="py-3.5 px-4">Kích thước</th>
                                        <th class="py-3.5 px-4">Ngày tải lên</th>
                                        <th class="py-3.5 px-5 text-right">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/25">
                                    <tr 
                                        v-for="item in orphans.data" 
                                        :key="item.id" 
                                        class="hover:bg-muted/15 transition-colors group"
                                    >
                                        <td class="py-3 px-5 text-center">
                                            <input 
                                                type="checkbox" 
                                                :checked="selectedIds.includes(item.id)" 
                                                @change="toggleSelect(item.id)" 
                                                class="size-4 rounded accent-orange-500 border-border cursor-pointer"
                                            />
                                        </td>
                                        <td class="py-3 px-4 flex items-center gap-3">
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
                                                <p class="font-bold text-xs text-slate-700 dark:text-slate-350 truncate" :title="item.file_name">
                                                    {{ item.file_name }}
                                                </p>
                                                <p class="text-[9px] text-muted-foreground font-mono truncate font-semibold mt-0.5" :title="item.file_path">
                                                    {{ item.disk.toUpperCase() }} · {{ item.file_path }}
                                                </p>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span v-if="item.restaurant_code" class="inline-flex flex-col">
                                                <span class="font-bold text-slate-700 dark:text-slate-300 text-xs">{{ item.restaurant_name }}</span>
                                                <span class="text-[9px] text-muted-foreground font-mono font-semibold mt-0.5">{{ item.restaurant_code }}</span>
                                            </span>
                                            <span v-else class="text-muted-foreground text-[10px] font-bold">Hệ thống (Global)</span>
                                        </td>
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center rounded-lg bg-orange-500/10 text-orange-650 dark:text-orange-400 border border-orange-500/15 px-2 py-0.5 text-[9px] font-bold">
                                                {{ getAttachableLabel(item.attachable_type) }}
                                                <span v-if="item.attachable_id" class="ml-1 font-mono font-bold">#{{ item.attachable_id }}</span>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 font-mono font-bold text-slate-700 dark:text-slate-350">
                                            {{ item.size_mb }} MB
                                        </td>
                                        <td class="py-3 px-4 text-muted-foreground text-[10px] font-semibold">
                                            {{ item.created_at }}
                                        </td>
                                        <td class="py-3 px-5 text-right">
                                            <button 
                                                @click="deleteSingle(item)" 
                                                class="size-8 flex items-center justify-center text-rose-500 rounded-lg hover:bg-rose-500/10 transition-colors opacity-0 group-hover:opacity-100 cursor-pointer"
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
                        <div v-if="orphans.last_page > 1" class="flex items-center justify-between border-t border-border/20 px-6 py-4 bg-muted/10">
                            <span class="text-[10px] text-muted-foreground font-semibold">
                                Trang {{ orphans.current_page }} / {{ orphans.last_page }} · Tổng {{ orphans.total }} tệp mồ côi
                            </span>
                            <div class="flex items-center gap-2">
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    :disabled="orphans.current_page <= 1"
                                    @click="navigatePage(orphans.current_page - 1)"
                                    class="cursor-pointer font-bold text-xs rounded-lg"
                                >
                                    <ChevronLeft class="size-4 mr-1" /> Trước
                                </Button>
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    :disabled="orphans.current_page >= orphans.last_page"
                                    @click="navigatePage(orphans.current_page + 1)"
                                    class="cursor-pointer font-bold text-xs rounded-lg"
                                >
                                    Sau <ChevronRight class="size-4 ml-1" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Column: AI Storage Advisor (4 columns) -->
            <div class="lg:col-span-4 flex flex-col gap-6">
                <!-- AI Storage Coach Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardContent class="p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-border/40 pb-3">
                            <h4 class="text-xs font-black text-muted-foreground uppercase tracking-wider">AI Storage Coach</h4>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase border animate-pulse"
                                :class="stats.total_count > 0 ? 'bg-amber-500/10 text-amber-600 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'"
                            >
                                {{ stats.total_count > 0 ? 'Cần Dọn Dẹp' : 'Tối Ưu' }}
                            </span>
                        </div>

                        <!-- Score Rank Layout -->
                        <div class="flex items-center gap-4 bg-muted/15 border border-border/30 rounded-2xl p-4">
                            <div class="size-14 rounded-xl border flex items-center justify-center text-xl font-black shadow-xs shrink-0"
                                :class="storageAdvice.color"
                            >
                                {{ stats.total_count === 0 ? 'A+' : stats.total_mb > 100 ? 'D' : 'B' }}
                            </div>
                            <div>
                                <h5 class="text-xs font-black text-slate-800 dark:text-slate-200">Xếp hạng: {{ storageAdvice.label }}</h5>
                                <p class="text-[10px] text-muted-foreground font-semibold mt-0.5 leading-normal">{{ storageAdvice.desc }}</p>
                            </div>
                        </div>

                        <!-- File Type Classification Progress Bar -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground">
                                <span>Phân loại định dạng tệp mồ côi</span>
                                <span>{{ imageRatio }}% Hình ảnh</span>
                            </div>
                            <!-- Progress Bar -->
                            <div class="h-2 w-full bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden flex">
                                <div class="h-full bg-orange-500" :style="{ width: imageRatio + '%' }" title="Hình ảnh mồ côi"></div>
                                <div class="h-full bg-slate-500 flex-1" title="Tài liệu & định dạng khác"></div>
                            </div>
                            <div class="flex items-center justify-between text-[9px] text-muted-foreground font-semibold">
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-orange-500 block"></span> Hình ảnh: {{ imageFilesCount }} tệp</span>
                                <span class="flex items-center gap-1"><span class="size-2 rounded-full bg-slate-500 block"></span> Khác: {{ otherFilesCount }} tệp</span>
                            </div>
                        </div>

                        <!-- Diagnostics recommendations -->
                        <div class="space-y-2.5 border-t border-border/40 pt-3">
                            <h5 class="text-[10px] font-black text-slate-700 dark:text-slate-350 uppercase tracking-wider">Khuyến nghị chẩn đoán:</h5>
                            
                            <!-- Advice 1 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div class="mt-0.5 shrink-0" :class="diskRecommendation.status === 'caution' ? 'text-amber-500' : 'text-emerald-500'">
                                    <AlertCircle v-if="diskRecommendation.status === 'caution'" class="size-3.5" />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p class="font-semibold text-slate-600 dark:text-slate-400 leading-normal">{{ diskRecommendation.text }}</p>
                            </div>

                            <!-- Advice 2 -->
                            <div class="flex items-start gap-2 text-[10px]">
                                <div class="mt-0.5 shrink-0" :class="fileCleanupRecommendation.status === 'warning' ? 'text-amber-500' : 'text-emerald-500'">
                                    <AlertTriangle v-if="fileCleanupRecommendation.status === 'warning'" class="size-3.5" />
                                    <CheckCircle2 v-else class="size-3.5" />
                                </div>
                                <p class="font-semibold text-slate-600 dark:text-slate-400 leading-normal">{{ fileCleanupRecommendation.text }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
