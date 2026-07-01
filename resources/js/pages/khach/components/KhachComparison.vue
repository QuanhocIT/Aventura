<script setup lang="ts">
import { Check, X, Minus } from 'lucide-vue-next';

interface CompareRow {
    feature: string;
    aventura: 'yes' | 'no' | 'partial';
    kiotviet: 'yes' | 'no' | 'partial';
    ipos: 'yes' | 'no' | 'partial';
    mobifb: 'yes' | 'no' | 'partial';
    highlight?: boolean;
}

const rows: CompareRow[] = [
    { feature: 'QR Order không cần app',         aventura: 'yes', kiotviet: 'partial', ipos: 'yes',     mobifb: 'partial', highlight: true  },
    { feature: 'Kitchen Display System',          aventura: 'yes', kiotviet: 'no',      ipos: 'partial', mobifb: 'no',      highlight: true  },
    { feature: 'AI phân tích doanh thu',          aventura: 'yes', kiotviet: 'no',      ipos: 'no',      mobifb: 'no',      highlight: true  },
    { feature: 'Quản lý kho theo lô',             aventura: 'yes', kiotviet: 'yes',     ipos: 'partial', mobifb: 'no'                        },
    { feature: 'Đồng bộ Realtime multi-branch',   aventura: 'yes', kiotviet: 'partial', ipos: 'yes',     mobifb: 'no'                        },
    { feature: 'Chấm công & lương tự động',       aventura: 'yes', kiotviet: 'partial', ipos: 'no',      mobifb: 'no'                        },
    { feature: 'Loyalty & điểm tích lũy',         aventura: 'yes', kiotviet: 'yes',     ipos: 'yes',     mobifb: 'partial'                   },
    { feature: 'Tích hợp VNPAY / ZaloPay',        aventura: 'yes', kiotviet: 'yes',     ipos: 'yes',     mobifb: 'partial'                   },
    { feature: 'Báo cáo fraud detection',         aventura: 'yes', kiotviet: 'no',      ipos: 'no',      mobifb: 'no',      highlight: true  },
    { feature: 'Gói Free thực sự dùng được',      aventura: 'yes', kiotviet: 'no',      ipos: 'no',      mobifb: 'no',      highlight: true  },
    { feature: 'Hỗ trợ 24/7 tiếng Việt',         aventura: 'yes', kiotviet: 'partial', ipos: 'partial', mobifb: 'no'                        },
];

const competitors = [
    { key: 'aventura',  label: 'Aventura',  isUs: true  },
    { key: 'kiotviet',  label: 'KiotViet',  isUs: false },
    { key: 'ipos',      label: 'iPOS',      isUs: false },
    { key: 'mobifb',    label: 'MobiF&B',   isUs: false },
] as const;

type CompetitorKey = typeof competitors[number]['key'];

function getValue(row: CompareRow, key: CompetitorKey) {
    return row[key];
}
</script>

<template>
    <section id="comparison" class="relative overflow-hidden py-20 lg:py-28 bg-muted/30">
        <!-- Background glow -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[700px] h-[400px] rounded-full bg-primary/5 blur-3xl" />
        </div>

        <div class="relative mx-auto max-w-7xl px-4 lg:px-8">
            <!-- Header -->
            <div class="reveal-on-scroll mb-12 text-center">
                <span class="inline-block rounded-full border border-primary/30 bg-primary/10 px-4 py-1.5 text-xs font-semibold uppercase tracking-widest text-primary mb-4">
                    So sánh
                </span>
                <h2 class="text-3xl font-bold text-foreground sm:text-4xl">
                    Tại sao chọn <span class="text-primary">Aventura</span>?
                </h2>
                <p class="mt-3 text-muted-foreground max-w-xl mx-auto">
                    So sánh trực tiếp với các phần mềm phổ biến tại thị trường Việt Nam.
                </p>
            </div>

            <!-- Table wrapper -->
            <div class="reveal-on-scroll overflow-x-auto rounded-2xl border border-border shadow-xl bg-card">
                <table class="w-full min-w-[640px] border-collapse text-sm">
                    <!-- Header row -->
                    <thead>
                        <tr class="border-b border-border">
                            <th class="w-2/5 py-4 pl-6 pr-4 text-left text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                Tính năng
                            </th>
                            <th
                                v-for="comp in competitors"
                                :key="comp.key"
                                class="px-4 py-4 text-center text-sm font-bold"
                                :class="comp.isUs
                                    ? 'bg-primary/10 text-primary border-x border-primary/20'
                                    : 'text-muted-foreground'"
                            >
                                <span v-if="comp.isUs" class="inline-flex items-center gap-1.5">
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-primary text-[10px] font-black text-primary-foreground">A</span>
                                    {{ comp.label }}
                                </span>
                                <span v-else>{{ comp.label }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(row, i) in rows"
                            :key="i"
                            class="border-b border-border transition-colors hover:bg-muted/40"
                            :class="row.highlight ? 'bg-accent/30' : ''"
                        >
                            <!-- Feature label -->
                            <td class="py-3.5 pl-6 pr-4">
                                <span class="text-foreground font-medium">{{ row.feature }}</span>
                                <span v-if="row.highlight" class="ml-2 inline-block rounded-full bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold text-primary">Độc quyền</span>
                            </td>

                            <!-- Values -->
                            <td
                                v-for="comp in competitors"
                                :key="comp.key"
                                class="px-4 py-3.5 text-center"
                                :class="comp.isUs ? 'bg-primary/5 border-x border-primary/10' : ''"
                            >
                                <!-- yes -->
                                <span v-if="getValue(row, comp.key) === 'yes'" class="inline-flex items-center justify-center">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full"
                                        :class="comp.isUs ? 'bg-primary/20' : 'bg-green-500/15'"
                                    >
                                        <Check class="h-3.5 w-3.5" :class="comp.isUs ? 'text-primary' : 'text-green-500'" />
                                    </span>
                                </span>
                                <!-- no -->
                                <span v-else-if="getValue(row, comp.key) === 'no'" class="inline-flex items-center justify-center">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-destructive/10">
                                        <X class="h-3.5 w-3.5 text-destructive/70" />
                                    </span>
                                </span>
                                <!-- partial -->
                                <span v-else-if="getValue(row, comp.key) === 'partial'" class="inline-flex items-center justify-center">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-muted">
                                        <Minus class="h-3.5 w-3.5 text-muted-foreground" />
                                    </span>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Legend -->
            <div class="mt-5 flex flex-wrap items-center justify-center gap-5 text-xs text-muted-foreground">
                <span class="flex items-center gap-1.5">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-primary/20"><Check class="h-2.5 w-2.5 text-primary" /></span>
                    Aventura có
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-green-500/15"><Check class="h-2.5 w-2.5 text-green-500" /></span>
                    Đối thủ có
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-muted"><Minus class="h-2.5 w-2.5 text-muted-foreground" /></span>
                    Có một phần
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-destructive/10"><X class="h-2.5 w-2.5 text-destructive/70" /></span>
                    Không có
                </span>
            </div>
        </div>
    </section>
</template>
