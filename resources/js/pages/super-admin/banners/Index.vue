<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';
import { ImageIcon, Trash2, ToggleLeft, ToggleRight, Upload, ExternalLink } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

declare const route: (name: string, params?: unknown) => string;

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
    { key: 'hero', label: 'Hero Banner', hint: '1920 Ã— 600px', color: 'from-violet-500 to-indigo-600' },
    { key: 'promo', label: 'Promo Banner', hint: '1200 Ã— 300px', color: 'from-amber-500 to-orange-600' },
] as const;

const filteredBanners = computed(() =>
    props.banners.filter((b) => b.slot === activeSlot.value)
);

// Upload form
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
    if (!file) return;
    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

function onDrop(e: DragEvent) {
    e.preventDefault();
    const file = e.dataTransfer?.files?.[0];
    if (!file) return;
    form.image = file;
    imagePreview.value = URL.createObjectURL(file);
}

function clearImage() {
    form.image = null;
    imagePreview.value = null;
}

function submitBanner() {
    form.post(route('superadmin.banners.store'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            imagePreview.value = null;
        },
    });
}

function toggleActive(banner: Banner) {
    router.patch(
        route('superadmin.banners.update', banner.id),
        { is_active: !banner.is_active },
        { preserveScroll: true }
    );
}

function deleteBanner(banner: Banner) {
    if (!confirm(`XÃ³a banner "${banner.title || 'nÃ y'}"?`)) return;
    router.delete(route('superadmin.banners.destroy', banner.id), { preserveScroll: true });
}

const currentSlotMeta = computed(() => slots.find((s) => s.key === activeSlot.value)!);
</script>

<template>
    <Head title="Quáº£n lÃ½ Banner" />

    <div class="min-h-screen bg-gray-950 p-6 text-white">
        <div class="mx-auto max-w-6xl space-y-6">

            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-white">Quáº£n lÃ½ Banner & Slideshow</h1>
                <p class="mt-1 text-sm text-gray-400">Upload áº£nh Ä‘á»ƒ thay Ä‘á»•i giao diá»‡n trang khÃ¡ch hÃ ng mÃ  khÃ´ng cáº§n Ä‘á»¥ng code.</p>
            </div>

            <!-- Slot tabs -->
            <div class="flex gap-3">
                <button
                    v-for="s in slots"
                    :key="s.key"
                    @click="selectSlot(s.key)"
                    :class="[
                        'flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-medium transition-all',
                        activeSlot === s.key
                            ? `bg-gradient-to-r ${s.color} text-white shadow-lg`
                            : 'bg-gray-800 text-gray-400 hover:bg-gray-700',
                    ]"
                >
                    <ImageIcon class="h-4 w-4" />
                    {{ s.label }}
                    <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ s.hint }}</span>
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                <!-- Upload form -->
                <Card class="border-gray-800 bg-gray-900">
                    <CardHeader>
                        <CardTitle class="text-base text-white">
                            ThÃªm {{ currentSlotMeta.label }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">

                        <!-- Dropzone -->
                        <div
                            ref="dropzone"
                            @dragover.prevent
                            @drop="onDrop"
                            class="relative cursor-pointer rounded-xl border-2 border-dashed border-gray-700 transition hover:border-indigo-500"
                        >
                            <label class="block cursor-pointer">
                                <input type="file" accept="image/jpg,image/jpeg,image/png,image/webp" class="hidden" @change="onFileChange" />
                                <div v-if="!imagePreview" class="flex flex-col items-center justify-center gap-2 py-10 text-gray-500">
                                    <Upload class="h-8 w-8" />
                                    <span class="text-sm">KÃ©o tháº£ hoáº·c click Ä‘á»ƒ chá»n áº£nh</span>
                                    <span class="text-xs">JPG, PNG, WebP Â· Tá»‘i Ä‘a 5MB Â· {{ currentSlotMeta.hint }}</span>
                                </div>
                                <div v-else class="relative">
                                    <img :src="imagePreview" class="max-h-48 w-full rounded-xl object-cover" />
                                    <button
                                        type="button"
                                        @click.prevent="clearImage"
                                        class="absolute right-2 top-2 rounded-full bg-red-600 p-1 text-white hover:bg-red-700"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                    </button>
                                </div>
                            </label>
                        </div>
                        <p v-if="form.errors.image" class="text-xs text-red-400">{{ form.errors.image }}</p>

                        <!-- Fields -->
                        <div class="space-y-3">
                            <div>
                                <Label class="text-gray-300">TiÃªu Ä‘á» <span class="text-gray-500">(tÃ¹y chá»n)</span></Label>
                                <Input v-model="form.title" placeholder="VÃ­ dá»¥: Khuyáº¿n mÃ£i thÃ¡ng 6" class="mt-1 border-gray-700 bg-gray-800 text-white" />
                            </div>
                            <div>
                                <Label class="text-gray-300">Subtitle <span class="text-gray-500">(tÃ¹y chá»n)</span></Label>
                                <Input v-model="form.subtitle" placeholder="MÃ´ táº£ ngáº¯n" class="mt-1 border-gray-700 bg-gray-800 text-white" />
                            </div>
                            <div>
                                <Label class="text-gray-300">URL khi click <span class="text-gray-500">(tÃ¹y chá»n)</span></Label>
                                <Input v-model="form.link_url" placeholder="https://..." class="mt-1 border-gray-700 bg-gray-800 text-white" />
                                <p v-if="form.errors.link_url" class="mt-1 text-xs text-red-400">{{ form.errors.link_url }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <Label class="text-gray-300">Hiá»‡u lá»±c tá»«</Label>
                                    <Input v-model="form.starts_at" type="date" class="mt-1 border-gray-700 bg-gray-800 text-white" />
                                </div>
                                <div>
                                    <Label class="text-gray-300">Háº¿t háº¡n</Label>
                                    <Input v-model="form.ends_at" type="date" class="mt-1 border-gray-700 bg-gray-800 text-white" />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <input id="is_active" v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-gray-600 bg-gray-700 text-indigo-500" />
                                <Label for="is_active" class="cursor-pointer text-gray-300">Hiá»ƒn thá»‹ ngay</Label>
                            </div>
                        </div>

                        <Button
                            @click="submitBanner"
                            :disabled="!form.image || form.processing"
                            :class="`w-full bg-gradient-to-r ${currentSlotMeta.color} hover:opacity-90 disabled:opacity-40`"
                        >
                            <Upload class="mr-2 h-4 w-4" />
                            {{ form.processing ? 'Äang upload...' : 'ThÃªm banner' }}
                        </Button>
                    </CardContent>
                </Card>

                <!-- Banner list -->
                <Card class="border-gray-800 bg-gray-900">
                    <CardHeader>
                        <CardTitle class="text-base text-white">
                            Banner hiá»‡n táº¡i
                            <Badge class="ml-2 bg-gray-700 text-gray-300">{{ filteredBanners.length }}</Badge>
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div v-if="filteredBanners.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-600">
                            <ImageIcon class="h-10 w-10 mb-2" />
                            <p class="text-sm">ChÆ°a cÃ³ banner nÃ o</p>
                        </div>

                        <div v-else class="space-y-3">
                            <div
                                v-for="banner in filteredBanners"
                                :key="banner.id"
                                class="flex items-start gap-3 rounded-xl border border-gray-800 bg-gray-800/50 p-3 transition hover:border-gray-700"
                            >
                                <!-- Thumbnail -->
                                <img
                                    :src="banner.image_url"
                                    class="h-16 w-24 flex-shrink-0 rounded-lg object-cover"
                                    :alt="banner.title ?? ''"
                                />

                                <!-- Info -->
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-white">
                                        {{ banner.title || '(KhÃ´ng cÃ³ tiÃªu Ä‘á»)' }}
                                    </p>
                                    <p v-if="banner.subtitle" class="truncate text-xs text-gray-400">{{ banner.subtitle }}</p>
                                    <a
                                        v-if="banner.link_url"
                                        :href="banner.link_url"
                                        target="_blank"
                                        class="mt-0.5 flex items-center gap-1 truncate text-xs text-indigo-400 hover:underline"
                                    >
                                        <ExternalLink class="h-3 w-3" />
                                        {{ banner.link_url }}
                                    </a>
                                    <div class="mt-1 flex items-center gap-2">
                                        <Badge :class="banner.is_active ? 'bg-green-900 text-green-300' : 'bg-gray-700 text-gray-400'" class="text-xs">
                                            {{ banner.is_active ? 'Äang hiá»ƒn thá»‹' : 'áº¨n' }}
                                        </Badge>
                                        <span v-if="banner.ends_at" class="text-xs text-gray-500">Ä‘áº¿n {{ banner.ends_at }}</span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-shrink-0 flex-col gap-1">
                                    <button
                                        @click="toggleActive(banner)"
                                        :title="banner.is_active ? 'áº¨n banner' : 'Hiá»ƒn thá»‹ banner'"
                                        class="rounded-lg p-1.5 transition"
                                        :class="banner.is_active ? 'text-green-400 hover:bg-green-900/30' : 'text-gray-500 hover:bg-gray-700'"
                                    >
                                        <ToggleRight v-if="banner.is_active" class="h-5 w-5" />
                                        <ToggleLeft v-else class="h-5 w-5" />
                                    </button>
                                    <button
                                        @click="deleteBanner(banner)"
                                        title="XÃ³a banner"
                                        class="rounded-lg p-1.5 text-gray-500 transition hover:bg-red-900/30 hover:text-red-400"
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
            <div class="rounded-xl border border-gray-800 bg-gray-900/50 p-4 text-sm text-gray-500">
                <span class="font-medium text-gray-400">LÆ°u Ã½:</span>
                Banner sáº½ hiá»ƒn thá»‹ ngay trÃªn trang <a href="/" target="_blank" class="text-indigo-400 hover:underline">trang chá»§</a> sau khi upload.
                Náº¿u cÃ³ nhiá»u banner cÃ¹ng slot, sáº½ tá»± Ä‘á»™ng cháº¡y slideshow (Ä‘á»•i áº£nh má»—i 4 giÃ¢y).
            </div>
        </div>
    </div>
</template>

