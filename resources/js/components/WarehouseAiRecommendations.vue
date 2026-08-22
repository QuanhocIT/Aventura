<script setup lang="ts">
import axios from 'axios';
import {
    AlertTriangle,
    ArrowRight,
    BrainCircuit,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    Sparkles,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type Context = 'overview' | 'stock' | 'requests' | 'receiving' | 'prices' | 'team';

const props = withDefaults(defineProps<{
    initialAi?: any;
    context?: Context;
    max?: number;
}>(), {
    context: 'overview',
    max: 3,
});

const ai = ref<any>(props.initialAi ?? null);
const loading = ref(!props.initialAi);

const contextSources: Record<Context, string[]> = {
    overview: [],
    stock: ['inventory_forecast', 'fefo_monitoring', 'demand_trend'],
    requests: ['supply_request_sla', 'fulfillment_kpi', 'demand_trend'],
    receiving: ['receiving_control', 'fefo_monitoring', 'fulfillment_kpi'],
    prices: ['price_governance', 'demand_trend', 'inventory_forecast'],
    team: ['warehouse_workload', 'supply_request_sla', 'receiving_control'],
};

const signals = computed(() => {
    const sourceFilter = contextSources[props.context];
    const allSignals = Array.isArray(ai.value?.signals) ? ai.value.signals : [];

    return allSignals
        .filter((signal: any) => !sourceFilter.length || sourceFilter.includes(signal.source))
        .slice(0, props.max);
});

const levelClass = computed(() => ({
    stable: 'border-emerald-500/25 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300',
    watch: 'border-amber-500/25 bg-amber-500/5 text-amber-700 dark:text-amber-300',
    risk: 'border-orange-500/25 bg-orange-500/5 text-orange-700 dark:text-orange-300',
    critical: 'border-rose-500/25 bg-rose-500/5 text-rose-700 dark:text-rose-300',
}[ai.value?.level as string] ?? 'border-indigo-500/25 bg-indigo-500/5 text-indigo-700 dark:text-indigo-300'));

const signalClass = (severity: string) => ({
    critical: 'border-rose-500/25 bg-rose-500/5',
    high: 'border-orange-500/25 bg-orange-500/5',
    medium: 'border-amber-500/25 bg-amber-500/5',
    low: 'border-indigo-500/25 bg-indigo-500/5',
}[severity] ?? 'border-border bg-muted/20');

const iconClass = (severity: string) => ({
    critical: 'bg-rose-500/15 text-rose-600 dark:text-rose-300',
    high: 'bg-orange-500/15 text-orange-600 dark:text-orange-300',
    medium: 'bg-amber-500/15 text-amber-600 dark:text-amber-300',
    low: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-300',
}[severity] ?? 'bg-muted text-muted-foreground');

const contextTitle: Record<Context, string> = {
    overview: 'AI ưu tiên vận hành',
    stock: 'AI gợi ý kiểm soát tồn kho',
    requests: 'AI gợi ý điều phối cấp phát',
    receiving: 'AI gợi ý tiếp nhận nguyên liệu',
    prices: 'AI gợi ý kiểm soát giá vốn',
    team: 'AI gợi ý điều phối đội kho',
};

onMounted(async () => {
    if (ai.value) {
        return;
    }

    try {
        const response = await axios.get('/api/warehouse/ai-recommendations');
        ai.value = response.data.ai ?? {};
    } catch {
        ai.value = {};
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Card class="border-indigo-500/25 bg-gradient-to-br from-indigo-500/5 via-background to-violet-500/5 shadow-sm">
        <CardHeader class="border-b border-indigo-500/15 py-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <CardTitle class="flex items-center gap-2 text-sm">
                    <BrainCircuit class="size-4 text-indigo-500 dark:text-indigo-300" />
                    {{ contextTitle[props.context] }}
                </CardTitle>
                <span v-if="ai" class="rounded-full border px-2.5 py-1 text-[10px] font-bold" :class="levelClass">
                    {{ ai.label || 'Đang phân tích' }}
                </span>
            </div>
        </CardHeader>
        <CardContent class="space-y-3 p-4">
            <div v-if="loading" class="flex items-center gap-2 text-xs text-muted-foreground">
                <LoaderCircle class="size-4 animate-spin" /> AI đang tổng hợp dữ liệu Kho Tổng...
            </div>
            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground">
                    <span>{{ ai?.summary || 'Chưa có đủ dữ liệu để đưa ra gợi ý.' }}</span>
                    <span v-if="ai?.confidence" class="shrink-0">Độ tin cậy {{ Math.round(Number(ai.confidence) * 100) }}%</span>
                </div>

                <div v-if="!signals.length" class="flex items-center gap-2 rounded-xl border border-dashed border-emerald-500/25 bg-emerald-500/5 p-3 text-xs text-emerald-700 dark:text-emerald-300">
                    <CheckCircle2 class="size-4 shrink-0" /> Chưa có ngoại lệ cần ưu tiên ở chức năng này.
                </div>
                <div v-for="signal in signals" :key="`${signal.source}-${signal.metric}-${signal.title}`" class="rounded-xl border p-3" :class="signalClass(signal.severity)">
                    <div class="flex items-start gap-3">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg" :class="iconClass(signal.severity)">
                            <AlertTriangle v-if="['critical', 'high'].includes(signal.severity)" class="size-4" />
                            <Clock3 v-else-if="signal.source === 'warehouse_workload' || signal.source === 'supply_request_sla'" class="size-4" />
                            <Sparkles v-else class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <p class="text-xs font-bold text-foreground">{{ signal.title }}</p>
                                <span class="text-[10px] font-bold uppercase text-muted-foreground">{{ signal.severity }}</span>
                            </div>
                            <p class="mt-1 text-[11px] leading-relaxed text-muted-foreground">{{ signal.evidence }}</p>
                            <p class="mt-1.5 text-[11px] font-semibold leading-relaxed text-foreground">{{ signal.advice }}</p>
                            <a v-if="signal.action_url" :href="signal.action_url" class="mt-2 inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 hover:underline dark:text-indigo-300">
                                {{ signal.action_label || 'Xem chi tiết' }} <ArrowRight class="size-3" />
                            </a>
                        </div>
                    </div>
                </div>
            </template>
            <p class="text-[10px] text-muted-foreground">Gợi ý chỉ hỗ trợ quyết định; Trưởng kho xác nhận trước khi thực hiện thao tác.</p>
        </CardContent>
    </Card>
</template>
