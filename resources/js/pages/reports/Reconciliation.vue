<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { 
  ChevronLeft, AlertTriangle, CheckCircle, 
  TrendingDown, DollarSign, CalendarDays 
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type ReconRow = {
  product_id: number;
  product_name: string;
  product_price: number;
  cost_price: number;
  cooked_qty: number;
  billed_qty: number;
  discrepancy: number;
  potential_loss_cost: number;
  potential_loss_retail: number;
};

type Totals = {
  total_cooked: number;
  total_billed: number;
  total_discrepancy: number;
  total_loss_cost: number;
  total_loss_retail: number;
};

const props = defineProps<{
  reconciliation: ReconRow[];
  totals: Totals;
  period: string;
  dateRange: { start: string; end: string };
}>();

const activePeriod = ref(props.period);

const changePeriod = (p: string) => {
  activePeriod.value = p;
  router.get('/reports/reconciliation', { period: p }, { preserveScroll: true });
};
</script>

<template>
  <Head title="Đối soát Bếp & POS · Aventura" />

  <div class="space-y-6 p-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <Button variant="ghost" size="icon" @click="router.get('/reports')" class="size-8">
            <ChevronLeft class="size-4" />
          </Button>
          <h1 class="text-2xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 dark:from-white dark:to-slate-300 bg-clip-text text-transparent">
            Đối soát Chéo: Bếp vs POS
          </h1>
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400">
          So sánh lượng món ăn đầu bếp thực tế đã làm (KDS) và hóa đơn thu ngân đã tính tiền (POS) từ {{ dateRange.start }} đến {{ dateRange.end }}
        </p>
      </div>

      <!-- Period selector -->
      <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-lg border w-fit">
        <button
          v-for="p in [{key: '7days', label: '7 ngày'}, {key: '30days', label: '30 ngày'}, {key: 'month', label: 'Tháng này'}]"
          :key="p.key"
          @click="changePeriod(p.key)"
          :class="['px-3 py-1.5 rounded-md text-xs font-medium transition-all duration-200', activePeriod === p.key ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-900 dark:hover:text-white']"
        >
          {{ p.label }}
        </button>
      </div>
    </div>

    <!-- Alert for potential fraud / leakage -->
    <div 
      v-if="totals.total_discrepancy > 0" 
      class="flex gap-3 bg-rose-50 border border-rose-200 dark:bg-rose-950/20 dark:border-rose-900/50 rounded-xl p-4 text-rose-800 dark:text-rose-400"
    >
      <AlertTriangle class="size-5 shrink-0 mt-0.5" />
      <div>
        <h4 class="font-bold text-sm">Phát hiện thất thoát món ăn!</h4>
        <p class="text-xs mt-1 leading-relaxed">
          Có tổng cộng <strong>{{ totals.total_discrepancy }} món ăn</strong> được chế biến trong bếp nhưng không có hóa đơn thanh toán tương ứng. 
          Điều này chỉ ra nguy cơ cao về việc thất thoát nguyên vật liệu do chế biến sai hỏng không ghi nhận, hoặc dấu hiệu gian lận tiền mặt tại quầy.
        </p>
      </div>
    </div>

    <!-- Metric Cards -->
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <Card class="bg-gradient-to-br from-white to-slate-50 dark:from-slate-950 dark:to-slate-900 border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
        <CardContent class="p-6">
          <div class="flex items-center justify-between space-y-0">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tổng Bếp Chế Biến</p>
            <div class="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400">
              <CalendarDays class="size-4" />
            </div>
          </div>
          <div class="mt-4 space-y-1">
            <h3 class="text-3xl font-black text-slate-900 dark:text-white">{{ totals.total_cooked }}</h3>
            <p class="text-[10px] text-slate-400">món đã nấu & đi đồ</p>
          </div>
        </CardContent>
      </Card>

      <Card class="bg-gradient-to-br from-white to-slate-50 dark:from-slate-950 dark:to-slate-900 border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
        <CardContent class="p-6">
          <div class="flex items-center justify-between space-y-0">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tổng POS Thanh Toán</p>
            <div class="p-2 rounded-lg bg-green-50 dark:bg-green-950/40 text-green-600 dark:text-green-400">
              <CheckCircle class="size-4" />
            </div>
          </div>
          <div class="mt-4 space-y-1">
            <h3 class="text-3xl font-black text-slate-900 dark:text-white">{{ totals.total_billed }}</h3>
            <p class="text-[10px] text-slate-400">món đã được tính tiền</p>
          </div>
        </CardContent>
      </Card>

      <Card class="bg-gradient-to-br from-white to-slate-50 dark:from-slate-950 dark:to-slate-900 border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
        <CardContent class="p-6">
          <div class="flex items-center justify-between space-y-0">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Số Lượng Thất Thoát</p>
            <div :class="['p-2 rounded-lg text-rose-600 dark:text-rose-400', totals.total_discrepancy > 0 ? 'bg-rose-50 dark:bg-rose-950/40' : 'bg-slate-50 dark:bg-slate-900']">
              <TrendingDown class="size-4" />
            </div>
          </div>
          <div class="mt-4 space-y-1">
            <h3 :class="['text-3xl font-black', totals.total_discrepancy > 0 ? 'text-rose-600 dark:text-rose-500' : 'text-slate-900 dark:text-white']">
              {{ totals.total_discrepancy }}
            </h3>
            <p class="text-[10px] text-slate-400">món chênh lệch lệch bếp</p>
          </div>
        </CardContent>
      </Card>

      <Card class="bg-gradient-to-br from-white to-slate-50 dark:from-slate-950 dark:to-slate-900 border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
        <CardContent class="p-6">
          <div class="flex items-center justify-between space-y-0">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Ước Tính Thất Thoát (Giá vốn)</p>
            <div :class="['p-2 rounded-lg text-rose-600 dark:text-rose-400', totals.total_loss_cost > 0 ? 'bg-rose-50 dark:bg-rose-950/40 animate-pulse' : 'bg-slate-50 dark:bg-slate-900']">
              <DollarSign class="size-4" />
            </div>
          </div>
          <div class="mt-4 space-y-1">
            <h3 :class="['text-2xl font-black', totals.total_loss_cost > 0 ? 'text-rose-600 dark:text-rose-500' : 'text-slate-900 dark:text-white']">
              {{ totals.total_loss_cost.toLocaleString() }}đ
            </h3>
            <p class="text-[10px] text-slate-400">Bán lẻ: {{ totals.total_loss_retail.toLocaleString() }}đ</p>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Main Reconciliation Table -->
    <Card class="border border-slate-200/60 dark:border-slate-800/60 shadow-sm">
      <CardHeader class="flex flex-row items-center justify-between">
        <div>
          <CardTitle class="text-lg font-bold">Danh sách món ăn lệch đối soát</CardTitle>
          <p class="text-xs text-slate-500 mt-1">Chi tiết chênh lệch và giá trị thất thoát của từng sản phẩm trong thực đơn</p>
        </div>
      </CardHeader>
      <CardContent class="p-0">
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider">
                <th class="px-6 py-4">Tên Món Ăn</th>
                <th class="px-6 py-4 text-center">Bếp Làm (KDS)</th>
                <th class="px-6 py-4 text-center">POS Bán</th>
                <th class="px-6 py-4 text-center">Chênh Lệch</th>
                <th class="px-6 py-4 text-right">Mất Mát (Giá Vốn)</th>
                <th class="px-6 py-4 text-right">Mất Mát (Bán Lẻ)</th>
                <th class="px-6 py-4 text-center">Trạng Thái</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
              <tr 
                v-for="row in reconciliation" 
                :key="row.product_id"
                class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
              >
                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                  {{ row.product_name }}
                </td>
                <td class="px-6 py-4 text-center font-medium">
                  {{ row.cooked_qty }}
                </td>
                <td class="px-6 py-4 text-center font-medium">
                  {{ row.billed_qty }}
                </td>
                <td class="px-6 py-4 text-center font-bold">
                  <span :class="row.discrepancy > 0 ? 'text-rose-600 dark:text-rose-500' : 'text-slate-500'">
                    {{ row.discrepancy > 0 ? `+${row.discrepancy}` : '0' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right font-medium text-slate-500">
                  {{ row.potential_loss_cost > 0 ? `${row.potential_loss_cost.toLocaleString()}đ` : '—' }}
                </td>
                <td class="px-6 py-4 text-right font-semibold">
                  <span :class="row.potential_loss_retail > 0 ? 'text-rose-600 dark:text-rose-500' : 'text-slate-500'">
                    {{ row.potential_loss_retail > 0 ? `${row.potential_loss_retail.toLocaleString()}đ` : '—' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-center">
                  <span 
                    v-if="row.discrepancy > 0" 
                    class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold rounded-full bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400 border border-rose-200/50 dark:border-rose-900/40"
                  >
                    <AlertTriangle class="size-3" /> Cảnh Báo Lệch
                  </span>
                  <span 
                    v-else 
                    class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold rounded-full bg-green-50 text-green-700 dark:bg-green-950/30 dark:text-green-400 border border-green-200/50 dark:border-green-900/40"
                  >
                    <CheckCircle class="size-3" /> Khớp Đúng
                  </span>
                </td>
              </tr>
              <tr v-if="reconciliation.length === 0">
                <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                  Không tìm thấy dữ liệu đối soát chéo trong thời gian được chọn.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  </div>
</template>
