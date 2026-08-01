<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Star, MessageSquarePlus, Send, CheckCircle2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const isOpen = defineModel<boolean>('open', { default: false });

const hoverRating = ref(0);

const form = useForm({
    rating: 5,
    category: 'service_plan',
    content: '',
});

const ratingLabels: Record<number, string> = {
    1: 'Rất không hài lòng 😞',
    2: 'Chưa tốt 😐',
    3: 'Bình thường 🙂',
    4: 'Hài lòng 😊',
    5: 'Rất tuyệt vời 🌟',
};

const categories = [
    { value: 'service_plan', label: 'Gói dịch vụ & Giá cước' },
    { value: 'pos_system', label: 'Vận hành POS & QR Ordering' },
    { value: 'customer_support', label: 'Hỗ trợ kỹ thuật & CSKH' },
    { value: 'feature_request', label: 'Yêu cầu tính năng mới' },
    { value: 'general', label: 'Góp ý chung' },
];

const isSubmitted = ref(false);

function submit() {
    form.post('/platform-feedback', {
        preserveScroll: true,
        onSuccess: () => {
            isSubmitted.value = true;
            setTimeout(() => {
                isOpen.value = false;
                isSubmitted.value = false;
                form.reset();
            }, 2000);
        },
    });
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent
            class="border-border/80 bg-background/95 backdrop-blur-md sm:max-w-lg"
        >
            <DialogHeader class="border-b border-border/40 pb-4">
                <DialogTitle
                    class="flex items-center gap-2 text-lg font-bold text-foreground"
                >
                    <div class="rounded-xl bg-primary/10 p-2 text-primary">
                        <MessageSquarePlus class="size-5" />
                    </div>
                    Đánh giá Dịch vụ & Nền tảng
                </DialogTitle>
                <DialogDescription
                    class="pt-1 text-xs leading-relaxed text-muted-foreground"
                >
                    Đóng góp ý kiến của quý doanh nghiệp giúp Aventura không
                    ngừng tối ưu các gói cước và tính năng phần mềm.
                </DialogDescription>
            </DialogHeader>

            <!-- Success confirmation state -->
            <div
                v-if="isSubmitted"
                class="flex animate-in flex-col items-center justify-center space-y-3 py-8 text-center duration-300 fade-in"
            >
                <div
                    class="flex size-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                >
                    <CheckCircle2 class="size-8" />
                </div>
                <h3 class="text-base font-bold text-foreground">
                    Gửi đánh giá thành công!
                </h3>
                <p class="max-w-xs text-xs text-muted-foreground">
                    Cảm ơn bạn đã đóng góp ý kiến. Phản hồi đã được chuyển trực
                    tiếp tới Ban Quản Trị SuperAdmin.
                </p>
            </div>

            <!-- Form state -->
            <form v-else @submit.prevent="submit" class="space-y-4 pt-2">
                <!-- Star Rating selector -->
                <div
                    class="space-y-2 rounded-2xl border border-border/30 bg-muted/30 p-4 text-center"
                >
                    <Label
                        class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        Mức độ hài lòng của bạn
                    </Label>
                    <div class="flex items-center justify-center gap-2 py-1">
                        <button
                            v-for="star in 5"
                            :key="star"
                            type="button"
                            @click="form.rating = star"
                            @mouseenter="hoverRating = star"
                            @mouseleave="hoverRating = 0"
                            class="cursor-pointer p-1.5 transition-transform hover:scale-125 focus:outline-none"
                        >
                            <Star
                                class="size-7 transition-colors"
                                :class="
                                    star <= (hoverRating || form.rating)
                                        ? 'fill-amber-400 text-amber-400 drop-shadow-xs'
                                        : 'text-muted-foreground/30'
                                "
                            />
                        </button>
                    </div>
                    <p
                        class="h-4 text-xs font-semibold text-amber-600 dark:text-amber-400"
                    >
                        {{ ratingLabels[hoverRating || form.rating] }}
                    </p>
                </div>

                <!-- Category select -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold text-muted-foreground"
                        >DANH MỤC ĐÁNH GIÁ</Label
                    >
                    <select
                        v-model="form.category"
                        class="flex h-10 w-full rounded-xl border border-border bg-background px-3 py-2 text-xs font-semibold text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <option
                            v-for="cat in categories"
                            :key="cat.value"
                            :value="cat.value"
                        >
                            {{ cat.label }}
                        </option>
                    </select>
                </div>

                <!-- Comment text area -->
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold text-muted-foreground"
                        >NỘI DUNG ĐÁNH GIÁ / GÓP Ý</Label
                    >
                    <textarea
                        v-model="form.content"
                        rows="4"
                        required
                        class="flex w-full resize-none rounded-xl border border-border bg-background px-3.5 py-2.5 text-xs text-foreground placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        placeholder="Hãy chia sẻ trải nghiệm của bạn về gói dịch vụ, tốc độ hệ thống, hoặc tính năng mong muốn cải thiện..."
                    />
                    <span
                        v-if="form.errors.content"
                        class="text-[11px] font-medium text-rose-500"
                    >
                        {{ form.errors.content }}
                    </span>
                </div>

                <div
                    class="flex items-center justify-end gap-2 border-t border-border/40 pt-2"
                >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="cursor-pointer rounded-xl text-xs font-bold"
                        @click="isOpen = false"
                    >
                        Hủy
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="form.processing || !form.content.trim()"
                        class="cursor-pointer gap-1.5 rounded-xl bg-primary text-xs font-bold text-primary-foreground shadow-sm"
                    >
                        <Send class="size-3.5" />
                        Gửi đánh giá
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
