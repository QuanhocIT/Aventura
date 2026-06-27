<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { Calendar, Clock, Trash2, ShieldAlert, ArrowLeft, Wrench } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PageHeader, DataTable, StatusBadge, AlertBanner, EmptyState } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    schedules: Array<{
        id: number;
        title: string;
        downtime_start: string;
        downtime_end: string;
        raw_start: string;
        raw_end: string;
        services: Array<string>;
        banner_message: string;
        status: string;
        notified_24h: boolean;
        notified_1h: boolean;
    }>;
    services: Array<{
        service_key: string;
        name: string;
    }>;
}>();

const form = useForm({
    title: '',
    downtime_start: '',
    downtime_end: '',
    services: [] as string[],
    banner_message: '',
});

function submit() {
    form.post('/super-admin/maintenance-schedules', {
        onSuccess: () => form.reset(),
    });
}

function cancelSchedule(id: number) {
    if (confirm('Bạn có chắc chắn muốn hủy lịch bảo trì này không?')) {
        router.delete(`/super-admin/maintenance-schedules/${id}`, {
            preserveScroll: true,
        });
    }
}

const statusColors: Record<string, string> = {
    scheduled: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300',
    active: 'bg-rose-100 text-rose-800 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 animate-pulse',
    completed: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
    cancelled: 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-950/40 dark:text-slate-300',
};

const statusLabels: Record<string, string> = {
    scheduled: 'Đã lên lịch',
    active: 'Đang bảo trì',
    completed: 'Đã hoàn thành',
    cancelled: 'Đã hủy',
};
</script>

<template>
    <Head title="Lịch bảo trì hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <PageHeader
            title="Lịch bảo trì hệ thống"
            subtitle="Lên lịch nâng cấp dịch vụ và tự động cảnh báo các tenant"
            :icon="Calendar"
        />

        <div class="grid gap-6 lg:grid-cols-[1.2fr,0.8fr]">
            <!-- Schedules List -->
            <div class="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base flex items-center gap-2">
                            <Clock class="size-4.5 text-blue-500" /> Danh sách lịch bảo trì
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-for="sch in schedules" :key="sch.id" class="rounded-xl border border-border/75 p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-muted/20 relative group">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h3 class="font-bold text-base text-foreground">{{ sch.title }}</h3>
                                    <span :class="['rounded-full border px-2.5 py-0.5 text-xs font-semibold shadow-sm', statusColors[sch.status] || 'bg-slate-100 text-slate-800']">
                                        {{ statusLabels[sch.status] ?? sch.status }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1"><Calendar class="size-3.5" /> Bắt đầu: {{ sch.downtime_start }}</span>
                                    <span class="flex items-center gap-1"><Clock class="size-3.5" /> Kết thúc: {{ sch.downtime_end }}</span>
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    Dịch vụ ảnh hưởng: 
                                    <span v-for="serv in sch.services" :key="serv" class="inline-block bg-slate-100 dark:bg-slate-800 border rounded px-1.5 py-0.5 text-[10px] font-mono font-bold mr-1">{{ serv === 'mysql' ? 'Hệ thống chính (MySQL)' : serv }}</span>
                                </div>
                                <p v-if="sch.banner_message" class="text-xs text-muted-foreground border-l-2 pl-2 border-indigo-500/50 italic">{{ sch.banner_message }}</p>
                                <div class="flex gap-2 text-[10px] text-muted-foreground font-semibold flex-wrap">
                                    <span :class="[sch.notified_24h ? 'text-emerald-600' : 'text-slate-400']">📧 Đã báo trước 24h</span>
                                    <span :class="[sch.notified_1h ? 'text-emerald-600' : 'text-slate-400']">📧 Đã báo trước 1h</span>
                                </div>
                            </div>
                            <Button v-if="sch.status === 'scheduled' || sch.status === 'active'" @click="cancelSchedule(sch.id)" variant="ghost" size="icon" class="text-rose-500 hover:text-rose-700 hover:bg-rose-50 dark:hover:bg-rose-950/20 shrink-0">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <p v-if="!schedules.length" class="py-12 text-center text-sm text-muted-foreground">Chưa có lịch bảo trì nào được đăng ký.</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Create Form -->
            <Card class="border-indigo-500/20 shadow-md">
                <CardHeader class="border-b bg-slate-50/50 dark:bg-slate-900/20">
                    <CardTitle class="text-base text-indigo-600 dark:text-indigo-400 flex items-center gap-2">
                        <Wrench class="size-5" /> Lên lịch bảo trì mới
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-6">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div class="grid gap-1.5">
                            <Label for="title">Tên chiến dịch bảo trì</Label>
                            <Input id="title" v-model="form.title" placeholder="Bảo trì nâng cấp Database Q3..." required />
                            <span v-if="form.errors.title" class="text-xs text-rose-500">{{ form.errors.title }}</span>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="downtime_start">Thời gian bắt đầu</Label>
                            <Input id="downtime_start" type="datetime-local" v-model="form.downtime_start" required />
                            <span v-if="form.errors.downtime_start" class="text-xs text-rose-500">{{ form.errors.downtime_start }}</span>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="downtime_end">Thời gian kết thúc (dự kiến)</Label>
                            <Input id="downtime_end" type="datetime-local" v-model="form.downtime_end" required />
                            <span v-if="form.errors.downtime_end" class="text-xs text-rose-500">{{ form.errors.downtime_end }}</span>
                        </div>

                        <div class="space-y-2">
                            <Label>Các dịch vụ bị khóa</Label>
                            <div class="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-3.5">
                                <div v-for="serv in services" :key="serv.service_key" class="flex items-start gap-2.5">
                                    <input
                                        type="checkbox"
                                        :id="'serv-' + serv.service_key"
                                        :value="serv.service_key"
                                        v-model="form.services"
                                        class="mt-1 size-4 accent-indigo-600 rounded"
                                    />
                                    <Label :for="'serv-' + serv.service_key" class="text-xs font-semibold cursor-pointer">
                                        {{ serv.name }}
                                        <span class="block text-[10px] text-muted-foreground font-normal">Khóa dịch vụ {{ serv.service_key }} khi đến giờ bảo trì.</span>
                                    </Label>
                                </div>
                            </div>
                            <span v-if="form.errors.services" class="text-xs text-rose-500">{{ form.errors.services }}</span>
                        </div>

                        <div class="grid gap-1.5">
                            <Label for="banner_message">Thông báo hiển thị cho tenant</Label>
                            <textarea
                                id="banner_message"
                                v-model="form.banner_message"
                                placeholder="Hệ thống sẽ bảo trì nâng cấp từ 02:00 - 04:00 ngày 25/06..."
                                class="min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            ></textarea>
                            <span v-if="form.errors.banner_message" class="text-xs text-rose-500">{{ form.errors.banner_message }}</span>
                        </div>

                        <Button type="submit" :disabled="form.processing" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold h-10 justify-center">
                            {{ form.processing ? 'Đang gửi...' : 'Đăng ký Lịch bảo trì' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
