<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    ImageIcon,
    Pencil,
    Trash2,
    ToggleLeft,
    ToggleRight,
    Upload,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { PageHeader, StatusBadge, EmptyState } from '@/components/super-admin';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirmDialog } from '@/composables/useConfirm';

interface Banner {
    id: number;
    slot: 'hero' | 'promo';
    title: string | null;
    subtitle: string | null;
    image_url: string;
    link_url: string | null;
    is_active: boolean;
    sort_order: number;
    starts_at: string | null;
    ends_at: string | null;
}

const props = defineProps<{ banners: Banner[] }>();

const activeSlot = ref<'hero' | 'promo'>('hero');

const slots = [
    {
        key: 'hero',
        label: 'Hero Banner',
        hint: '1920 × 600px',
        color: 'from-violet-500 to-indigo-600',
    },
    {
        key: 'promo',
        label: 'Promo Banner',
        hint: '1200 × 300px',
        color: 'from-amber-500 to-orange-600',
    },
] as const;

const filteredBanners = computed(() =>
    props.banners
        .filter((b) => b.slot === activeSlot.value)
        .sort((a, b) => a.sort_order - b.sort_order),
);

// ── Upload form ──────────────────────────────────────────────
const form = useForm({
    slot: 'hero' as 'hero' | 'promo',
    image: null as File | null,
    title: '',
    subtitle: '',
    link_url: '',
    is_active: true,
    starts_at: '',
    ends_at: '',
});

const imagePreview = ref<string | null>(null);
const dropzone = ref<HTMLDivElement | null>(null);

function selectSlot(slot: 'hero' | 'promo') {
    activeSlot.value = slot;
    form.slot = slot;
}

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

function onDrop(e: DragEvent) {
    e.preventDefault();
    const file = e.dataTransfer?.files?.[0];

    if (!file) {
        return;
    }

    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

function clearImage() {
    form.image = null;
    imagePreview.value = null;
}

function submitBanner() {
    form.post('/super-admin/banners', {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            imagePreview.value = null;
        },
    });
}

// ── Toggle / Delete ──────────────────────────────────────────
function toggleActive(banner: Banner) {
    router.patch(
        `/super-admin/banners/${banner.id}`,
        { is_active: !banner.is_active },
        { preserveScroll: true },
    );
}

async function deleteBanner(banner: Banner) {
    if (
        !(await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Xóa banner "${banner.title || 'này'}"?`,
        }))
    ) {
        return;
    }

    router.delete(`/super-admin/banners/${banner.id}`, {
        preserveScroll: true,
    });
}

// ── Reorder ──────────────────────────────────────────────────
function moveUp(banner: Banner) {
    const list = filteredBanners.value;
    const index = list.findIndex((b) => b.id === banner.id);

    if (index <= 0) {
        return;
    }

    const items = list.map((b, i) => {
        if (i === index - 1) {
            return { id: b.id, sort_order: list[index].sort_order };
        }

        if (i === index) {
            return { id: b.id, sort_order: list[index - 1].sort_order };
        }

        return { id: b.id, sort_order: b.sort_order };
    });

    router.post(
        '/super-admin/banners/reorder',
        { items },
        { preserveScroll: true },
    );
}

function moveDown(banner: Banner) {
    const list = filteredBanners.value;
    const index = list.findIndex((b) => b.id === banner.id);

    if (index >= list.length - 1) {
        return;
    }

    const items = list.map((b, i) => {
        if (i === index) {
            return { id: b.id, sort_order: list[index + 1].sort_order };
        }

        if (i === index + 1) {
            return { id: b.id, sort_order: list[index].sort_order };
        }

        return { id: b.id, sort_order: b.sort_order };
    });

    router.post(
        '/super-admin/banners/reorder',
        { items },
        { preserveScroll: true },
    );
}

// ── Edit dialog ──────────────────────────────────────────────
const editingBanner = ref<Banner | null>(null);

const editForm = useForm({
    title: '',
    subtitle: '',
    link_url: '',
    is_active: true,
    starts_at: '',
    ends_at: '',
    image: null as File | null,
});

const editImagePreview = ref<string | null>(null);

function openEdit(banner: Banner) {
    editingBanner.value = banner;
    editForm.title = banner.title ?? '';
    editForm.subtitle = banner.subtitle ?? '';
    editForm.link_url = banner.link_url ?? '';
    editForm.is_active = banner.is_active;
    editForm.starts_at = banner.starts_at ?? '';
    editForm.ends_at = banner.ends_at ?? '';
    editForm.image = null;
    editImagePreview.value = null;
}

function closeEdit() {
    editingBanner.value = null;
    editImagePreview.value = null;
    editForm.reset();
}

function onEditFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    editForm.image = file;
    editImagePreview.value = URL.createObjectURL(file);
}

function clearEditImage() {
    editForm.image = null;
    editImagePreview.value = null;
}

function submitEdit() {
    if (!editingBanner.value) {
        return;
    }

    editForm.patch(`/super-admin/banners/${editingBanner.value.id}`, {
        forceFormData: true,
        onSuccess: () => closeEdit(),
    });
}

const currentSlotMeta = computed(
    () => slots.find((s) => s.key === activeSlot.value)!,
);
</script>

<template>
    <Head title="Quản lý Banner" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <div class="space-y-6">
            <!-- Header -->
            <PageHeader
                title="Quản lý Banner & Slideshow"
                subtitle="Upload ảnh để thay đổi giao diện trang khách hàng mà không cần đụng code."
                :icon="ImageIcon"
            />

            <!-- Slot tabs -->
            <div class="flex gap-3">
                <button
                    v-for="s in slots"
                    :key="s.key"
                    @click="selectSlot(s.key)"
                    :class="[
                        'flex cursor-pointer items-center gap-2 rounded-xl border px-5 py-3 text-sm font-medium transition-all',
                        activeSlot === s.key
                            ? `bg-gradient-to-r ${s.color} border-transparent text-white shadow-lg`
                            : 'border-border bg-card text-muted-foreground hover:bg-muted/70',
                    ]"
                >
                    <ImageIcon class="h-4 w-4" />
                    {{ s.label }}
                    <span
                        class="rounded-full bg-muted-foreground/10 px-2 py-0.5 text-xs font-semibold"
                        >{{ s.hint }}</span
                    >
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Upload form -->
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader>
                        <CardTitle class="text-base text-foreground">
                            Thêm {{ currentSlotMeta.label }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Dropzone -->
                        <div
                            ref="dropzone"
                            @dragover.prevent
                            @drop="onDrop"
                            class="relative cursor-pointer rounded-xl border-2 border-dashed border-border bg-muted/20 transition hover:border-indigo-500"
                        >
                            <label class="block cursor-pointer">
                                <input
                                    type="file"
                                    accept="image/jpg,image/jpeg,image/png,image/webp"
                                    class="hidden"
                                    @change="onFileChange"
                                />
                                <div
                                    v-if="!imagePreview"
                                    class="flex flex-col items-center justify-center gap-2 py-10 text-muted-foreground"
                                >
                                    <Upload class="h-8 w-8" />
                                    <span class="text-sm font-medium"
                                        >Kéo thả hoặc click để chọn ảnh</span
                                    >
                                    <span class="text-xs"
                                        >JPG, PNG, WebP · Tối đa 5MB ·
                                        {{ currentSlotMeta.hint }}</span
                                    >
                                </div>
                                <div v-else class="relative">
                                    <img
                                        :src="imagePreview"
                                        class="max-h-48 w-full rounded-xl object-cover"
                                    />
                                    <button
                                        type="button"
                                        @click.prevent="clearImage"
                                        class="absolute top-2 right-2 rounded-full bg-red-600 p-1 text-white hover:bg-red-700"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </button>
                                </div>
                            </label>
                        </div>
                        <p
                            v-if="form.errors.image"
                            class="text-xs text-red-400"
                        >
                            {{ form.errors.image }}
                        </p>

                        <!-- Fields -->
                        <div class="space-y-3">
                            <div>
                                <Label class="text-foreground"
                                    >Tiêu đề
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(tùy chọn)</span
                                    ></Label
                                >
                                <Input
                                    v-model="form.title"
                                    placeholder="Ví dụ: Khuyến mãi tháng 6"
                                    class="mt-1 rounded-xl border-border bg-background text-foreground"
                                />
                            </div>
                            <div>
                                <Label class="text-foreground"
                                    >Subtitle
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(tùy chọn)</span
                                    ></Label
                                >
                                <Input
                                    v-model="form.subtitle"
                                    placeholder="Mô tả ngắn"
                                    class="mt-1 rounded-xl border-border bg-background text-foreground"
                                />
                            </div>
                            <div>
                                <Label class="text-foreground"
                                    >URL khi click
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(tùy chọn)</span
                                    ></Label
                                >
                                <Input
                                    v-model="form.link_url"
                                    placeholder="https://..."
                                    class="mt-1 rounded-xl border-border bg-background text-foreground"
                                />
                                <p
                                    v-if="form.errors.link_url"
                                    class="mt-1 text-xs text-red-400"
                                >
                                    {{ form.errors.link_url }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <Label class="text-foreground"
                                        >Hiệu lực từ</Label
                                    >
                                    <Input
                                        v-model="form.starts_at"
                                        type="date"
                                        class="mt-1 rounded-xl border-border bg-background text-foreground"
                                    />
                                </div>
                                <div>
                                    <Label class="text-foreground"
                                        >Hết hạn</Label
                                    >
                                    <Input
                                        v-model="form.ends_at"
                                        type="date"
                                        class="mt-1 rounded-xl border-border bg-background text-foreground"
                                    />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input
                                    id="is_active"
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer rounded border-border text-primary"
                                />
                                <Label
                                    for="is_active"
                                    class="cursor-pointer text-muted-foreground select-none"
                                    >Hiển thị ngay</Label
                                >
                            </div>
                        </div>

                        <Button
                            @click="submitBanner"
                            :disabled="!form.image || form.processing"
                            :class="`w-full bg-gradient-to-r ${currentSlotMeta.color} hover:opacity-90 disabled:opacity-40`"
                        >
                            <Upload class="mr-2 h-4 w-4" />
                            {{
                                form.processing
                                    ? 'Đang upload...'
                                    : 'Thêm banner'
                            }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Banner list -->
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader>
                        <CardTitle class="text-base text-foreground">
                            Banner hiện tại
                            <Badge
                                class="ml-2 bg-muted-foreground/15 font-bold text-muted-foreground"
                                >{{ filteredBanners.length }}</Badge
                            >
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="filteredBanners.length === 0"
                            class="flex flex-col items-center justify-center py-12 text-muted-foreground/60"
                        >
                            <ImageIcon class="mb-2 h-10 w-10" />
                            <p class="text-sm">Chưa có banner nào</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="(banner, index) in filteredBanners"
                                :key="banner.id"
                                class="flex items-start gap-3 rounded-xl border border-border bg-card p-3 transition hover:bg-muted/30"
                            >
                                <!-- Thumbnail -->
                                <img
                                    :src="banner.image_url"
                                    class="h-16 w-24 flex-shrink-0 rounded-lg object-cover"
                                    :alt="banner.title ?? ''"
                                />

                                <!-- Info -->
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-bold text-foreground"
                                    >
                                        {{
                                            banner.title || '(Không có tiêu đề)'
                                        }}
                                    </p>
                                    <p
                                        v-if="banner.subtitle"
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ banner.subtitle }}
                                    </p>
                                    <a
                                        v-if="banner.link_url"
                                        :href="banner.link_url"
                                        target="_blank"
                                        class="mt-0.5 flex items-center gap-1 truncate text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                    >
                                        <ExternalLink class="h-3 w-3" />
                                        {{ banner.link_url }}
                                    </a>
                                    <div class="mt-1 flex items-center gap-2">
                                        <span
                                            v-if="banner.is_active"
                                            class="inline-flex items-center rounded-full border border-emerald-200/50 bg-emerald-50 px-2 py-0.5 text-[9px] font-black text-emerald-600 uppercase dark:bg-emerald-950/30 dark:text-emerald-400"
                                        >
                                            Đang hiển thị
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center rounded-full border bg-zinc-100 px-2 py-0.5 text-[9px] font-black text-zinc-500 uppercase dark:bg-zinc-800/80"
                                        >
                                            Ẩn
                                        </span>
                                        <span
                                            v-if="banner.ends_at"
                                            class="text-xs text-muted-foreground"
                                            >đến {{ banner.ends_at }}</span
                                        >
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-shrink-0 flex-col gap-1">
                                    <!-- Move up/down -->
                                    <button
                                        @click="moveUp(banner)"
                                        :disabled="index === 0"
                                        title="Lên trên"
                                        class="cursor-pointer rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
                                    >
                                        <ArrowUp class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        @click="moveDown(banner)"
                                        :disabled="
                                            index === filteredBanners.length - 1
                                        "
                                        title="Xuống dưới"
                                        class="cursor-pointer rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-30"
                                    >
                                        <ArrowDown class="h-3.5 w-3.5" />
                                    </button>
                                    <!-- Edit -->
                                    <button
                                        @click="openEdit(banner)"
                                        title="Sửa banner"
                                        class="cursor-pointer rounded-lg p-1.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </button>
                                    <!-- Toggle -->
                                    <button
                                        @click="toggleActive(banner)"
                                        :title="
                                            banner.is_active
                                                ? 'Ẩn banner'
                                                : 'Hiển thị banner'
                                        "
                                        class="cursor-pointer rounded-lg p-1.5 transition"
                                        :class="
                                            banner.is_active
                                                ? 'text-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-950/20'
                                                : 'text-muted-foreground hover:bg-muted'
                                        "
                                    >
                                        <ToggleRight
                                            v-if="banner.is_active"
                                            class="h-5 w-5"
                                        />
                                        <ToggleLeft v-else class="h-5 w-5" />
                                    </button>
                                    <!-- Delete -->
                                    <button
                                        @click="deleteBanner(banner)"
                                        title="Xóa banner"
                                        class="cursor-pointer rounded-lg p-1.5 text-rose-500 transition hover:bg-rose-50 dark:hover:bg-rose-950/20"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Preview note -->
            <div
                class="rounded-2xl border border-border bg-card p-4 text-xs text-muted-foreground"
            >
                <span class="font-bold text-foreground">Lưu ý:</span>
                Banner sẽ hiển thị ngay trên trang
                <a
                    href="/"
                    target="_blank"
                    class="text-indigo-650 font-semibold hover:underline dark:text-indigo-400"
                    >trang chủ</a
                >
                sau khi upload. Nếu có nhiều banner cùng slot, sẽ tự động chạy
                slideshow (đổi ảnh mỗi 4 giây).
            </div>
        </div>
    </div>

    <!-- Edit Banner Dialog -->
    <Dialog
        :open="!!editingBanner"
        @update:open="
            (v) => {
                if (!v) closeEdit();
            }
        "
    >
        <DialogContent
            class="rounded-3xl border border-border bg-card text-foreground sm:max-w-md"
        >
            <DialogHeader>
                <DialogTitle class="text-foreground">Sửa Banner</DialogTitle>
            </DialogHeader>

            <div class="space-y-4 py-2">
                <!-- Image replacement -->
                <div>
                    <Label class="text-foreground"
                        >Thay ảnh
                        <span class="font-normal text-muted-foreground"
                            >(để trống nếu giữ nguyên)</span
                        ></Label
                    >
                    <div
                        class="mt-1 rounded-xl border-2 border-dashed border-border bg-muted/20 transition hover:border-indigo-500"
                    >
                        <label class="block cursor-pointer">
                            <input
                                type="file"
                                accept="image/jpg,image/jpeg,image/png,image/webp"
                                class="hidden"
                                @change="onEditFileChange"
                            />
                            <div
                                v-if="!editImagePreview"
                                class="flex flex-col items-center justify-center gap-2 py-6 text-muted-foreground"
                            >
                                <Upload class="h-6 w-6" />
                                <span class="text-xs"
                                    >Click để chọn ảnh mới · JPG, PNG, WebP ·
                                    Tối đa 5MB</span
                                >
                            </div>
                            <div v-else class="relative">
                                <img
                                    :src="editImagePreview"
                                    class="max-h-36 w-full rounded-xl object-cover"
                                />
                                <button
                                    type="button"
                                    @click.prevent="clearEditImage"
                                    class="absolute top-2 right-2 rounded-full bg-red-600 p-1 text-white hover:bg-red-700"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>
                        </label>
                    </div>
                    <p
                        v-if="editForm.errors.image"
                        class="mt-1 text-xs text-red-400"
                    >
                        {{ editForm.errors.image }}
                    </p>
                </div>

                <div>
                    <Label class="text-foreground">Tiêu đề</Label>
                    <Input
                        v-model="editForm.title"
                        class="mt-1 rounded-xl border-border bg-background text-foreground"
                    />
                </div>
                <div>
                    <Label class="text-foreground">Subtitle</Label>
                    <Input
                        v-model="editForm.subtitle"
                        class="mt-1 rounded-xl border-border bg-background text-foreground"
                    />
                </div>
                <div>
                    <Label class="text-foreground">URL khi click</Label>
                    <Input
                        v-model="editForm.link_url"
                        placeholder="https://..."
                        class="mt-1 rounded-xl border-border bg-background text-foreground"
                    />
                    <p
                        v-if="editForm.errors.link_url"
                        class="mt-1 text-xs text-red-400"
                    >
                        {{ editForm.errors.link_url }}
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <Label class="text-foreground">Hiệu lực từ</Label>
                        <Input
                            v-model="editForm.starts_at"
                            type="date"
                            class="mt-1 rounded-xl border-border bg-background text-foreground"
                        />
                    </div>
                    <div>
                        <Label class="text-foreground">Hết hạn</Label>
                        <Input
                            v-model="editForm.ends_at"
                            type="date"
                            class="mt-1 rounded-xl border-border bg-background text-foreground"
                        />
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        id="edit_is_active"
                        v-model="editForm.is_active"
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer rounded border-border text-primary"
                    />
                    <Label
                        for="edit_is_active"
                        class="cursor-pointer text-muted-foreground select-none"
                        >Hiển thị</Label
                    >
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    variant="ghost"
                    class="rounded-xl text-muted-foreground hover:bg-muted"
                    @click="closeEdit"
                    >Huỷ</Button
                >
                <Button
                    :disabled="editForm.processing"
                    class="rounded-xl bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="submitEdit"
                >
                    {{ editForm.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
