<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Camera, CheckCircle2, Circle, ClipboardCheck, Loader2, Plus, Trash2, TrendingUp,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    templates: any[];
    completions: Record<number, any>;
    stats: Record<number, { total: number; completed: number; percent: number }>;
    date: string;
}>();

const page = usePage();
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
toast.success(flash.success);
}

    if (flash?.error) {
toast.error(flash.error);
}
});

const completions = ref<Record<number, any>>({ ...props.completions });
const completing = ref<number | null>(null);

// Type labels
const typeLabel: Record<string, string> = {
    opening: 'Mở cửa', closing: 'Đóng cửa', attp: 'ATTP / Vệ sinh', custom: 'Tùy chỉnh',
};
const typeColor: Record<string, string> = {
    opening: 'bg-green-100 text-green-800', closing: 'bg-blue-100 text-blue-800',
    attp: 'bg-red-100 text-red-800', custom: 'bg-gray-100 text-gray-700',
};

// Photo capture
const photoRef = ref<string | null>(null);
const showPhotoDialog = ref(false);
const photoItemId = ref<number | null>(null);
const videoRef = ref<HTMLVideoElement | null>(null);
const canvasRef = ref<HTMLCanvasElement | null>(null);
let stream: MediaStream | null = null;

async function openCamera(itemId: number) {
    photoItemId.value = itemId;
    showPhotoDialog.value = true;
    photoRef.value = null;
    await new Promise(r => setTimeout(r, 200));

    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });

        if (videoRef.value) {
videoRef.value.srcObject = stream;
}
    } catch {
 toast.error('Không thể truy cập camera.'); 
}
}

function capturePhoto() {
    if (!videoRef.value || !canvasRef.value) {
return;
}

    const ctx = canvasRef.value.getContext('2d');
    canvasRef.value.width = videoRef.value.videoWidth;
    canvasRef.value.height = videoRef.value.videoHeight;
    ctx?.drawImage(videoRef.value, 0, 0);
    photoRef.value = canvasRef.value.toDataURL('image/jpeg', 0.85);
    stream?.getTracks().forEach(t => t.stop());
}

function closeCamera() {
    stream?.getTracks().forEach(t => t.stop());
    showPhotoDialog.value = false;
    photoRef.value = null;
}

async function submitPhotoItem() {
    if (!photoItemId.value) {
return;
}

    await completeItem(photoItemId.value, photoRef.value);
    closeCamera();
}

// Complete item
async function completeItem(itemId: number, photo: string | null = null) {
    completing.value = itemId;

    try {
        const { data } = await axios.post('/operations-checklist/complete', {
            item_id: itemId, photo, date: props.date, notes: null,
        });

        if (data.success) {
            completions.value[itemId] = data.completion;
            toast.success('Đã hoàn thành!');
        }
    } catch (e: any) {
        toast.error(e.response?.data?.message ?? 'Có lỗi xảy ra.');
    } finally {
 completing.value = null; 
}
}

async function uncompleteItem(itemId: number) {
    try {
        await axios.post('/operations-checklist/uncomplete', { item_id: itemId, date: props.date });
        delete completions.value[itemId];
        toast.success('Đã bỏ đánh dấu.');
    } catch {
 toast.error('Có lỗi.'); 
}
}

function isCompleted(itemId: number): boolean {
    return !!completions.value[itemId];
}

function completedPercent(templateId: number): number {
    const template = props.templates.find(t => t.id === templateId);

    if (!template) {
return 0;
}

    const total = template.items.length;
    const done = template.items.filter((i: any) => isCompleted(i.id)).length;

    return total > 0 ? Math.round((done / total) * 100) : 0;
}

// Create template
const showCreateDialog = ref(false);
const createForm = useForm({
    name: '', type: 'opening' as string,
    items: [{ title: '', requires_photo: false }] as { title: string; requires_photo: boolean }[],
});

function addItem() {
 createForm.items.push({ title: '', requires_photo: false }); 
}
function removeItem(idx: number) {
 createForm.items.splice(idx, 1); 
}

function submitTemplate() {
    createForm.post('/operations-checklist/templates', {
        onSuccess: () => {
            showCreateDialog.value = false;
            createForm.reset();
            createForm.items = [{ title: '', requires_photo: false }];
        },
    });
}

const totalTemplates = computed(() => props.templates.length);

const totalAllItems = computed(() => {
    return props.templates.reduce((sum, t) => sum + (t.items?.length ?? 0), 0);
});

const totalCompletedItems = computed(() => {
    return props.templates.reduce((sum, t) => {
        const done = t.items?.filter((i: any) => isCompleted(i.id)).length ?? 0;

        return sum + done;
    }, 0);
});

const overallPercent = computed(() => {
    const total = totalAllItems.value;

    return total > 0 ? Math.round((totalCompletedItems.value / total) * 100) : 0;
});
</script>

<template>
    <Head title="Checklist Vận Hành" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- HEADER -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <ClipboardCheck class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Checklist Vận Hành Hàng Ngày</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Ngày: {{ new Date(date).toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' }) }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button 
                    @click="showCreateDialog = true" 
                    class="h-10 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold flex items-center gap-1.5"
                >
                    <Plus class="size-4" />
                    Tạo checklist mới
                </Button>
            </div>
        </div>

        <!-- KPI STATS CARDS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Total Checklists -->
            <Card class="shadow-xs hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-slate-400">Tổng mẫu quy trình</CardDescription>
                    <ClipboardCheck class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-slate-800 dark:text-slate-100">{{ totalTemplates }}</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">quy trình đang được áp dụng</p>
                </CardContent>
            </Card>

            <!-- Overall Completion Rate -->
            <Card class="shadow-xs border-emerald-100 dark:border-emerald-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-500">Tiến độ vận hành</CardDescription>
                    <CheckCircle2 class="size-4 text-emerald-600 dark:text-emerald-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ overallPercent }}%</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">mức độ hoàn thành hôm nay</p>
                </CardContent>
            </Card>

            <!-- Completed Items Ratio -->
            <Card class="shadow-xs border-indigo-100 dark:border-indigo-950/20 hover:translate-y-[-2px] transition-transform">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-indigo-500">Hạng mục đã làm</CardDescription>
                    <TrendingUp class="size-4 text-indigo-600 dark:text-indigo-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <span class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ totalCompletedItems }}/{{ totalAllItems }}</span>
                    <p class="mt-0.5 text-xs text-muted-foreground">việc đã được đánh dấu xong</p>
                </CardContent>
            </Card>
        </div>

        <!-- CDP sub-navigation (Tab bar style representation) -->
        <div class="flex items-center gap-2 border-b pb-2">
            <button 
                type="button"
                class="px-4 py-2 text-xs font-bold border-b-2 border-indigo-600 text-indigo-600 focus:outline-none"
            >
                📋 Bảng checklist vận hành
            </button>
        </div>

        <!-- Checklist cards -->
        <div v-for="template in templates" :key="template.id" class="space-y-4">
            <Card class="shadow-md rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/45 p-6 hover:translate-y-[-2px] transition-transform duration-200">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-border/60">
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            {{ template.name }}
                            <Badge :class="typeColor[template.type]" class="text-xs font-semibold">{{ typeLabel[template.type] }}</Badge>
                        </h3>
                        <p class="text-xs text-slate-500 mt-1 font-medium">{{ completedPercent(template.id) }}% hoàn thành</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="h-2 w-28 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div class="h-full bg-indigo-500 transition-all duration-300" :style="{ width: completedPercent(template.id) + '%' }"></div>
                        </div>
                        <span class="text-xs font-bold" :class="completedPercent(template.id) === 100 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-500'">
                            {{ template.items.filter((i: any) => isCompleted(i.id)).length }}/{{ template.items.length }}
                        </span>
                    </div>
                </div>
                <CardContent class="p-0 pt-2 divide-y divide-border/60">
                    <div v-for="item in template.items" :key="item.id"
                        class="flex items-center gap-3 py-3.5 hover:bg-muted/15 transition-colors">
                        <!-- Checkbox -->
                        <button
                            v-if="!isCompleted(item.id)"
                            @click="item.requires_photo ? openCamera(item.id) : completeItem(item.id)"
                            :disabled="completing === item.id"
                            class="shrink-0 focus:outline-none"
                        >
                            <Loader2 v-if="completing === item.id" class="size-5 animate-spin text-muted-foreground" />
                            <Circle v-else class="size-5 text-slate-400 hover:text-indigo-500 transition-colors" />
                        </button>
                        <button v-else @click="uncompleteItem(item.id)" class="shrink-0 focus:outline-none">
                            <CheckCircle2 class="size-5 text-indigo-500" />
                        </button>

                        <!-- Title -->
                        <div class="flex-1 min-w-0">
                            <p :class="['text-xs', isCompleted(item.id) ? 'line-through text-muted-foreground' : 'font-bold text-slate-700 dark:text-slate-200']">
                                {{ item.title }}
                            </p>
                            <p v-if="isCompleted(item.id) && completions[item.id]" class="text-[10px] text-muted-foreground mt-0.5">
                                ✔️ {{ completions[item.id].completed_by?.name }} lúc {{ new Date(completions[item.id].completed_at).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }) }}
                            </p>
                        </div>

                        <!-- Photo badge -->
                        <Badge v-if="item.requires_photo" variant="outline" class="text-[10px] py-0 px-1.5 gap-1 shrink-0 bg-slate-50 dark:bg-slate-800">
                            <Camera class="size-3" /> Ảnh
                        </Badge>
                    </div>
                </CardContent>
            </Card>
        </div>

        <p v-if="!templates.length" class="text-center text-slate-500 py-16 text-xs font-semibold">
            Chưa có checklist nào. Nhấn "Tạo checklist" để bắt đầu.
        </p>
    </div>

    <!-- Photo capture dialog -->
    <Dialog v-model:open="showPhotoDialog">
        <DialogContent class="max-w-sm">
            <DialogHeader><DialogTitle>Chụp ảnh xác nhận</DialogTitle></DialogHeader>
            <div class="space-y-3">
                <video v-if="!photoRef" ref="videoRef" autoplay playsinline class="w-full rounded-lg bg-black aspect-[4/3]"></video>
                <img v-else :src="photoRef" class="w-full rounded-lg" />
                <canvas ref="canvasRef" class="hidden"></canvas>
            </div>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="closeCamera">Hủy</Button>
                <Button v-if="!photoRef" @click="capturePhoto" class="gap-1.5"><Camera class="size-4" /> Chụp</Button>
                <Button v-else @click="submitPhotoItem" class="gap-1.5"><CheckCircle2 class="size-4" /> Xác nhận</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Create template dialog -->
    <Dialog v-model:open="showCreateDialog">
        <DialogContent class="max-w-lg max-h-[80vh] overflow-y-auto">
            <DialogHeader><DialogTitle>Tạo Checklist mới</DialogTitle></DialogHeader>
            <form @submit.prevent="submitTemplate" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label>Tên checklist</Label>
                        <Input v-model="createForm.name" placeholder="Checklist mở cửa" required />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Loại</Label>
                        <select v-model="createForm.type" class="h-9 rounded-md border bg-background px-3 text-sm">
                            <option value="opening">Mở cửa</option>
                            <option value="closing">Đóng cửa</option>
                            <option value="attp">ATTP / Vệ sinh</option>
                            <option value="custom">Tùy chỉnh</option>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <Label>Các mục kiểm tra</Label>
                    <div v-for="(item, idx) in createForm.items" :key="idx" class="flex items-center gap-2">
                        <Input v-model="item.title" :placeholder="'Mục ' + (idx + 1)" class="flex-1" required />
                        <label class="flex items-center gap-1 cursor-pointer text-xs whitespace-nowrap">
                            <input type="checkbox" v-model="item.requires_photo" class="rounded" />
                            <Camera class="size-3" />
                        </label>
                        <Button v-if="createForm.items.length > 1" variant="ghost" size="sm" class="text-red-500 shrink-0" type="button" @click="removeItem(idx)">
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                    <Button variant="outline" size="sm" type="button" @click="addItem" class="gap-1.5 w-full">
                        <Plus class="size-3.5" /> Thêm mục
                    </Button>
                </div>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="showCreateDialog = false">Hủy</Button>
                    <Button type="submit" :disabled="createForm.processing">
                        {{ createForm.processing ? 'Đang tạo...' : 'Tạo checklist' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
