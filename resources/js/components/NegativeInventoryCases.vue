<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type NegativeCase = {
    id: number;
    branch_name?: string | null;
    ingredient_name?: string | null;
    unit_symbol?: string | null;
    status:
        | 'open'
        | 'in_progress'
        | 'pending_owner_approval'
        | 'pending_verification';
    negative_quantity: number;
    on_hand: number;
    estimated_value: number;
    detected_at?: string | null;
    auto_plan?: string | null;
    handling_plan?: string | null;
    responsible_user_name?: string | null;
    expected_restock_at?: string | null;
};

const props = defineProps<{
    cases?: NegativeCase[];
    title?: string;
}>();

const activeId = ref<number | null>(null);
const plans = ref<Record<number, string>>({});
const rootCauses = ref<Record<number, string>>({});
const dates = ref<Record<number, string>>({});
const busyId = ref<number | null>(null);
const errorMessage = ref('');

const cases = computed(() => props.cases ?? []);

function planDraft(item: NegativeCase): string {
    return plans.value[item.id] ?? item.handling_plan ?? item.auto_plan ?? '';
}

function formatQuantity(value: number): string {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(
        value,
    );
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(value || 0);
}

async function savePlan(item: NegativeCase): Promise<void> {
    errorMessage.value = '';
    const handlingPlan = planDraft(item).trim();

    if (handlingPlan.length < 10) {
        errorMessage.value = 'Phương án xử lý cần tối thiểu 10 ký tự.';

        return;
    }

    busyId.value = item.id;

    try {
        await axios.post(
            `/api/inventory/negative-stock-cases/${item.id}/plan`,
            {
                handling_plan: handlingPlan,
                root_cause_code: 'unknown',
                root_cause: rootCauses.value[item.id] || null,
                expected_restock_at: dates.value[item.id] || null,
            },
        );
        activeId.value = null;
        await router.reload({ only: ['negativeStockCases'] });
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors ?? {})?.flat()?.[0] ||
            'Không thể lưu phương án xử lý.';
    } finally {
        busyId.value = null;
    }
}

async function submitVerification(item: NegativeCase): Promise<void> {
    const note = window.prompt(
        'Ghi chú gửi đối chiếu (tối thiểu 10 ký tự):',
        'Đã nhập bù/điều chỉnh và kiểm tra tồn thực tế không còn âm.',
    );

    if (!note) {
        return;
    }

    errorMessage.value = '';
    busyId.value = item.id;

    try {
        await axios.post(
            `/api/inventory/negative-stock-cases/${item.id}/submit-verification`,
            { note },
        );
        await router.reload({ only: ['negativeStockCases', 'safety'] });
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors ?? {})?.flat()?.[0] ||
            'Chưa thể gửi đối chiếu. Hãy kiểm tra tồn đã về 0 hoặc dương và có giao dịch bù.';
    } finally {
        busyId.value = null;
    }
}

async function verifyCase(item: NegativeCase): Promise<void> {
    const note = window.prompt(
        'Ghi chú xác minh (tối thiểu 10 ký tự):',
        'Đã đối chiếu giao dịch và xác nhận tồn thực tế chính xác.',
    );

    if (!note) {
        return;
    }

    errorMessage.value = '';
    busyId.value = item.id;

    try {
        await axios.post(
            `/api/inventory/negative-stock-cases/${item.id}/verify`,
            {
                resolution_type: 'verified',
                resolution_note: note,
            },
        );
        await router.reload({ only: ['negativeStockCases', 'safety'] });
    } catch (error: any) {
        errorMessage.value =
            error?.response?.data?.message ||
            Object.values(error?.response?.data?.errors ?? {})?.flat()?.[0] ||
            'Chưa thể xác minh hồ sơ.';
    } finally {
        busyId.value = null;
    }
}
</script>

<template>
    <Card
        class="bg-amber-50/50 dark:bg-amber-950/20"
        :class="
            cases.length
                ? 'border-amber-300 dark:border-amber-700'
                : 'border-emerald-300 dark:border-emerald-700'
        "
    >
        <CardHeader class="pb-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <CardTitle
                    class="flex items-center gap-2 text-base font-bold"
                    :class="
                        cases.length
                            ? 'text-amber-800 dark:text-amber-300'
                            : 'text-emerald-800 dark:text-emerald-300'
                    "
                >
                    <AlertTriangle class="size-4" />
                    {{ title || 'Hồ sơ âm nguyên liệu cần xử lý' }}
                    <span
                        class="rounded-full px-2 py-0.5 text-xs"
                        :class="
                            cases.length
                                ? 'bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-200'
                                : 'bg-emerald-200 text-emerald-900 dark:bg-emerald-900 dark:text-emerald-200'
                        "
                    >
                        {{ cases.length }}
                    </span>
                </CardTitle>
                <Link
                    href="/inventory/negative-stock"
                    class="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-300"
                    >Mở trung tâm xử lý →</Link
                >
            </div>
        </CardHeader>
        <CardContent class="space-y-3">
            <div
                v-if="!cases.length"
                class="rounded-md border border-dashed border-emerald-300 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-300"
            >
                Hiện chưa có hồ sơ âm nguyên liệu tại khu vực này. Khi tồn thực
                tế xuống dưới 0, hồ sơ sẽ tự động xuất hiện tại đây.
            </div>

            <p v-else class="text-xs text-amber-900/80 dark:text-amber-200/80">
                Hệ thống đã ghi nhận giao dịch thực tế. Cần lập phương án
                bù/điều chỉnh và chỉ chốt sau khi tồn không còn âm.
            </p>

            <div
                v-for="item in cases"
                :key="item.id"
                class="rounded-lg border border-amber-200 bg-white p-3 dark:border-amber-800 dark:bg-background"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold">
                            {{ item.ingredient_name || 'Nguyên liệu' }}
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">
                            <span v-if="item.branch_name"
                                >{{ item.branch_name }} ·
                            </span>
                            <template v-if="item.on_hand < 0">
                                Âm {{ formatQuantity(item.negative_quantity) }}
                                {{ item.unit_symbol || '' }}
                            </template>
                            <template v-else>
                                Đã bù tồn, chờ xác nhận chốt
                            </template>
                            · Giá trị ước tính
                            {{ formatCurrency(item.estimated_value) }}
                        </div>
                    </div>
                    <span
                        class="rounded-full px-2 py-1 text-[11px] font-semibold"
                        :class="
                            item.status === 'open'
                                ? 'bg-red-100 text-red-700'
                                : item.status === 'pending_owner_approval'
                                  ? 'bg-purple-100 text-purple-700'
                                  : item.status === 'pending_verification'
                                    ? 'bg-cyan-100 text-cyan-700'
                                    : 'bg-blue-100 text-blue-700'
                        "
                    >
                        {{
                            item.status === 'open'
                                ? 'Chưa lập phương án'
                                : item.status === 'pending_owner_approval'
                                  ? 'Chờ Chủ doanh nghiệp duyệt'
                                  : item.status === 'pending_verification'
                                    ? 'Chờ đối chiếu độc lập'
                                    : 'Đang xử lý'
                        }}
                    </span>
                </div>

                <div
                    class="mt-3 rounded-md bg-muted/50 p-2 text-xs text-muted-foreground"
                >
                    <span class="font-medium text-foreground">Gợi ý:</span>
                    {{ item.auto_plan }}
                </div>

                <div v-if="activeId === item.id" class="mt-3 space-y-2">
                    <textarea
                        v-model="plans[item.id]"
                        rows="3"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm ring-offset-background outline-none focus:ring-2 focus:ring-ring"
                        placeholder="Ví dụ: Nhập bù từ Kho Tổng, kiểm kê lại khu mát, đối chiếu đơn bán ngày..."
                    />
                    <textarea
                        v-model="rootCauses[item.id]"
                        rows="2"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm ring-offset-background outline-none focus:ring-2 focus:ring-ring"
                        placeholder="Mô tả nguyên nhân/bằng chứng đã kiểm tra (bắt buộc trước khi gửi đối chiếu)..."
                    />
                    <div class="flex flex-wrap items-center gap-2">
                        <label
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <CalendarClock class="size-3.5" />
                            Dự kiến nhập bù
                            <input
                                v-model="dates[item.id]"
                                type="date"
                                class="rounded border bg-background px-2 py-1 text-xs"
                            />
                        </label>
                        <Button
                            size="sm"
                            :disabled="busyId === item.id"
                            @click="savePlan(item)"
                        >
                            <ClipboardCheck class="mr-1 size-3.5" /> Lưu phương
                            án
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            @click="activeId = null"
                            >Hủy</Button
                        >
                    </div>
                </div>

                <div v-else class="mt-3 flex flex-wrap items-center gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        @click="activeId = item.id"
                    >
                        <ClipboardCheck class="mr-1 size-3.5" />
                        {{
                            item.handling_plan
                                ? 'Cập nhật phương án'
                                : 'Lập phương án'
                        }}
                    </Button>
                    <Button
                        v-if="
                            item.status === 'in_progress' && item.on_hand >= 0
                        "
                        size="sm"
                        variant="outline"
                        :disabled="busyId === item.id"
                        @click="submitVerification(item)"
                    >
                        <CheckCircle2 class="mr-1 size-3.5" /> Gửi đối chiếu
                    </Button>
                    <Button
                        v-if="
                            item.status === 'pending_verification' &&
                            item.on_hand >= 0
                        "
                        size="sm"
                        variant="ghost"
                        :disabled="busyId === item.id"
                        @click="verifyCase(item)"
                    >
                        <CheckCircle2 class="mr-1 size-3.5" /> Xác minh & chốt
                    </Button>
                </div>
            </div>

            <p v-if="errorMessage" class="text-xs font-medium text-red-600">
                {{ errorMessage }}
            </p>
        </CardContent>
    </Card>
</template>
