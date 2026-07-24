<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Gift, Clock, CheckCircle, Tag, Copy } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    restaurant: { id: number; name: string };
    customer: { id: number; name: string; phone: string };
    available: Array<{
        id: number;
        code: string;
        discount_type: string;
        discount_value: number;
        min_order_amount: number;
        expires_at: string;
        expires_at_raw: string;
        source: string;
    }>;
    used: Array<{
        id: number;
        code: string;
        discount_type: string;
        discount_value: number;
        used_at: string;
    }>;
    claimable: Array<{
        id: number;
        name: string;
        code: string;
        type: string;
        value: number;
        end_date: string | null;
    }>;
}>();

function copyCode(code: string) {
    navigator.clipboard.writeText(code);
    toast.success('Đã sao chép mã!');
}

function claimPromo(promoId: number) {
    router.post(
        `/customer/coupons/${props.restaurant.id}/${props.customer.phone}/claim`,
        { promotion_id: promoId },
    );
}

function formatDiscount(type: string, value: number) {
    return type === 'percent'
        ? `${value}%`
        : `${new Intl.NumberFormat('vi-VN').format(value)}₫`;
}

const activeTab = ref<'available' | 'browse' | 'used'>('available');
</script>

<template>
    <Head :title="`Mã KM — ${restaurant.name}`" />

    <div class="mx-auto max-w-lg px-4 py-6">
        <div class="mb-6 text-center">
            <Gift class="mx-auto mb-2 size-10 text-primary" />
            <h1 class="text-xl font-bold">Ví Khuyến Mãi</h1>
            <p class="text-sm text-muted-foreground">
                {{ restaurant.name }} · {{ customer.name }}
            </p>
        </div>

        <div class="mb-5 flex rounded-xl bg-muted/50 p-1">
            <button
                v-for="tab in [
                    { key: 'available', label: `Có sẵn (${available.length})` },
                    { key: 'browse', label: 'Nhận mã' },
                    { key: 'used', label: 'Đã dùng' },
                ]"
                :key="tab.key"
                :class="[
                    'flex-1 rounded-lg py-2 text-xs font-bold transition-all',
                    activeTab === tab.key
                        ? 'bg-card text-foreground shadow-sm'
                        : 'text-muted-foreground',
                ]"
                @click="activeTab = tab.key as any"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Available -->
        <div v-if="activeTab === 'available'" class="space-y-3">
            <div
                v-for="c in available"
                :key="c.id"
                class="rounded-2xl border bg-card p-4 transition-all hover:shadow-md"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="rounded-lg bg-primary/10 px-3 py-1 text-lg font-black text-primary"
                    >
                        {{ formatDiscount(c.discount_type, c.discount_value) }}
                    </span>
                    <button
                        class="flex items-center gap-1 rounded-lg border px-2 py-1 text-[10px] font-bold text-muted-foreground hover:bg-muted"
                        @click="copyCode(c.code)"
                    >
                        <Copy class="size-3" /> {{ c.code }}
                    </button>
                </div>
                <div
                    class="mt-3 flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <Clock class="size-3" />
                    <span>Hết hạn: {{ c.expires_at }}</span>
                </div>
                <div
                    v-if="c.min_order_amount > 0"
                    class="mt-1 text-[10px] text-muted-foreground"
                >
                    Đơn tối thiểu:
                    {{
                        new Intl.NumberFormat('vi-VN').format(
                            c.min_order_amount,
                        )
                    }}₫
                </div>
            </div>
            <p
                v-if="!available.length"
                class="py-10 text-center text-sm text-muted-foreground"
            >
                Chưa có mã nào. Xem tab "Nhận mã" để lấy!
            </p>
        </div>

        <!-- Browse claimable -->
        <div v-if="activeTab === 'browse'" class="space-y-3">
            <div
                v-for="p in claimable"
                :key="p.id"
                class="rounded-2xl border bg-card p-4"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold">{{ p.name }}</h3>
                        <span
                            class="mt-1 inline-block rounded-md bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary"
                        >
                            {{ formatDiscount(p.type, p.value) }}
                        </span>
                    </div>
                    <Button size="sm" @click="claimPromo(p.id)" class="gap-1">
                        <Gift class="size-3.5" /> Nhận
                    </Button>
                </div>
                <p
                    v-if="p.end_date"
                    class="mt-2 text-[10px] text-muted-foreground"
                >
                    Đến {{ p.end_date }}
                </p>
            </div>
            <p
                v-if="!claimable.length"
                class="py-10 text-center text-sm text-muted-foreground"
            >
                Hiện chưa có chương trình nào.
            </p>
        </div>

        <!-- Used -->
        <div v-if="activeTab === 'used'" class="space-y-3">
            <div
                v-for="c in used"
                :key="c.id"
                class="rounded-2xl border bg-card/50 p-4 opacity-60"
            >
                <div class="flex items-center justify-between">
                    <span class="text-sm font-bold line-through">{{
                        formatDiscount(c.discount_type, c.discount_value)
                    }}</span>
                    <span class="font-mono text-[10px] text-muted-foreground">{{
                        c.code
                    }}</span>
                </div>
                <div
                    class="mt-1 flex items-center gap-1 text-[10px] text-emerald-600"
                >
                    <CheckCircle class="size-3" /> Đã sử dụng {{ c.used_at }}
                </div>
            </div>
            <p
                v-if="!used.length"
                class="py-10 text-center text-sm text-muted-foreground"
            >
                Chưa sử dụng mã nào.
            </p>
        </div>
    </div>
</template>
