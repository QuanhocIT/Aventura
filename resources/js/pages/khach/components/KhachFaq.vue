<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

defineProps<{
    canRegister: boolean;
}>();

const faq = [
    {
        q: 'Tôi có thể dùng thử trước khi trả tiền không?',
        a: 'Hoàn toàn. Gói Free không yêu cầu thẻ tín dụng — đăng ký ngay và vận hành thực tế với 1 chi nhánh, 10 bàn, 5 nhân viên. Nâng gói bất kỳ lúc nào khi cần mở rộng.',
    },
    {
        q: 'Dữ liệu nhà hàng của tôi có được bảo mật không?',
        a: 'Có. Mỗi nhà hàng có tenant scope riêng — dữ liệu cô lập tuyệt đối. Mọi thao tác nhạy cảm (xóa đơn, thay đổi giá) đều được ghi vào audit log không thể xóa và có dấu vết IP.',
    },
    {
        q: 'Nâng gói hoặc hủy gói có mất dữ liệu không?',
        a: 'Không. Toàn bộ lịch sử đơn hàng, kho và nhân viên được giữ nguyên khi chuyển gói. Hạ gói sẽ khóa tính năng nâng cao nhưng không xóa bất kỳ dữ liệu nào.',
    },
    {
        q: 'Nếu gặp sự cố, hỗ trợ kỹ thuật ra sao?',
        a: 'Chatbot tích hợp trả lời 24/7 cho câu hỏi thường gặp. Ngoài ra có hệ thống ticket — team hỗ trợ phản hồi trong vòng 4 giờ làm việc (giờ hành chính).',
    },
    {
        q: 'Aventura có phù hợp với chuỗi nhiều chi nhánh không?',
        a: 'Có. Gói Max và Ultra hỗ trợ multi-branch với dữ liệu tách biệt theo tenant. Một tài khoản quản lý tất cả chi nhánh, báo cáo hợp nhất theo thời gian thực.',
    },
    {
        q: 'Tích hợp với máy in hóa đơn và phần cứng POS không?',
        a: 'Hệ thống hỗ trợ in bill qua mạng LAN. Với QR order, khách gọi món thẳng từ điện thoại nên giảm phụ thuộc vào máy tính tiền truyền thống.',
    },
];

const openFaqIndex = ref<number | null>(null);

function toggleFaq(i: number) {
    openFaqIndex.value = openFaqIndex.value === i ? null : i;
}
</script>

<template>
    <!-- ── FAQ Accordion ─────────────────────────────────────── -->
    <section class="px-4 py-10 lg:px-8 lg:py-12">
        <div class="mx-auto max-w-7xl">
            <div
                class="reveal-on-scroll flex flex-col items-start justify-between gap-6 lg:flex-row lg:items-end"
            >
                <div class="max-w-xl">
                    <Badge
                        variant="outline"
                        class="mb-3 border-amber-400/35 bg-amber-400/5 text-amber-300"
                        >FAQ</Badge
                    >
                    <h2
                        class="heading-section text-gradient-brand text-3xl font-semibold"
                    >
                        Câu hỏi thường gặp
                    </h2>
                    <p class="mt-3 text-muted-foreground">
                        Những thắc mắc phổ biến nhất từ chủ nhà hàng trước khi
                        bắt đầu dùng Aventura.
                    </p>
                </div>
                <Button
                    v-if="canRegister"
                    as-child
                    variant="outline"
                    size="sm"
                    class="shrink-0 border-amber-400/35 bg-amber-400/5 text-amber-200 hover:bg-amber-400/10 hover:text-amber-100"
                >
                    <Link :href="register()">Bắt đầu miễn phí →</Link>
                </Button>
            </div>
            <div
                class="reveal-on-scroll mt-8 divide-y divide-border overflow-hidden rounded-xl border border-border"
            >
                <div v-for="(item, i) in faq" :key="item.q">
                    <button
                        @click="toggleFaq(i)"
                        class="group flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition-colors hover:bg-amber-400/5"
                    >
                        <span
                            class="text-sm font-medium text-amber-100/90 transition-colors group-hover:text-amber-300"
                            >{{ item.q }}</span
                        >
                        <ChevronRight
                            class="size-4 flex-shrink-0 transition-transform duration-200"
                            :class="
                                openFaqIndex === i
                                    ? 'rotate-90 text-amber-300'
                                    : 'text-amber-400/70'
                            "
                        />
                    </button>
                    <Transition name="faq-collapse">
                        <div
                            v-show="openFaqIndex === i"
                            class="border-t border-border/50 bg-muted/20 px-5 py-4"
                        >
                            <p
                                class="text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ item.a }}
                            </p>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* FAQ collapse accordion transition */
.faq-collapse-enter-active,
.faq-collapse-leave-active {
    transition:
        max-height 0.35s cubic-bezier(0.4, 0, 0.2, 1),
        opacity 0.3s ease,
        padding 0.3s ease;
    max-height: 250px;
    overflow: hidden;
}
.faq-collapse-enter-from,
.faq-collapse-leave-to {
    max-height: 0 !important;
    opacity: 0;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}
</style>
