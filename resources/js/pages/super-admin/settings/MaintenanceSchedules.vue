<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Calendar, Clock, Trash2, ShieldAlert, ArrowLeft, Wrench, CheckCircle2, AlertTriangle, AlertCircle, PlayCircle, Bell, Server, Check } from 'lucide-vue-next';
import { toast } from 'vue-sonner';
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
        onSuccess: () => {
            form.reset();
            toast.success('Đăng ký lịch bảo trì thành công!');
        },
        onError: () => {
            toast.error('Lên lịch bảo trì thất bại. Vui lòng kiểm tra lại!');
        }
    });
}

function toggleService(key: string) {
    const idx = form.services.indexOf(key);
    if (idx > -1) {
        form.services.splice(idx, 1);
    } else {
        form.services.push(key);
    }
}

function cancelSchedule(id: number) {
    if (confirm('Bạn có chắc chắn muốn hủy lịch bảo trì này không?')) {
        router.delete(`/super-admin/maintenance-schedules/${id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã hủy lịch bảo trì thành công!'),
            onError: () => toast.error('Hủy lịch bảo trì thất bại!')
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
const downtimeAdvice = computed(() => {
    if (!form.downtime_start) return { status: 'neutral', text: 'Vui lòng chọn thời gian bắt đầu bảo trì.' };
    const startHour = new Date(form.downtime_start).getHours();
    if (startHour >= 6 && startHour <= 22) {
        return { status: 'warning', text: `Khung giờ ${startHour}:00 nằm trong khoảng cao điểm của hệ thống SaaS (6h - 22h). Khuyến cáo dời lịch sang đêm khuya (23h - 5h) để giảm ảnh hưởng.` };
    }
    return { status: 'ok', text: 'Khung giờ bắt đầu bảo trì đêm khuya tối ưu, ít gây gián đoạn nhất.' };
});

const durationAdvice = computed(() => {
    if (!form.downtime_start || !form.downtime_end) return { status: 'neutral', text: 'Nhập thời gian bắt đầu và kết thúc.' };
    const start = new Date(form.downtime_start).getTime();
    const end = new Date(form.downtime_end).getTime();
    const diffHrs = (end - start) / (1000 * 60 * 60);
    if (diffHrs < 0) {
        return { status: 'error', text: 'Thời gian kết thúc không thể trước thời gian bắt đầu.' };
    }
    if (diffHrs > 4) {
        return { status: 'warning', text: `Thời lượng bảo trì dài (${Math.round(diffHrs * 10) / 10} giờ). Đòi hỏi chuẩn bị kỹ kịch bản rollback và thông báo chi tiết đến các tenant.` };
    }
    return { status: 'ok', text: `Thời lượng bảo trì tối ưu (${Math.round(diffHrs * 10) / 10} giờ).` };
});

const servicesAdvice = computed(() => {
    if (!form.services.length) {
        return { status: 'caution', text: 'Chưa có dịch vụ nào bị khóa. Hãy chọn ít nhất một dịch vụ bị khóa để hệ thống ngắt truy cập tạm thời.' };
    }
    return { status: 'ok', text: `Có ${form.services.length} dịch vụ sẽ tự động khóa khi tới giờ bảo trì.` };
});

const bannerAdvice = computed(() => {
    if (!form.banner_message) {
        return { status: 'caution', text: 'Chưa soạn thảo tin nhắn cảnh báo. Tenants sẽ không thấy thông báo mô tả trên hệ thống.' };
    }
    return { status: 'ok', text: 'Tin nhắn cảnh báo đã sẵn sàng.' };
});

const hasMaintenanceWarnings = computed(() => {
    return downtimeAdvice.value.status === 'warning' ||
           durationAdvice.value.status === 'warning' ||
           durationAdvice.value.status === 'error' ||
           servicesAdvice.value.status === 'caution' ||
           bannerAdvice.value.status === 'caution';
});
</script>

<template>
    <Head title="Lịch bảo trì hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Lịch bảo trì hệ thống"
            subtitle="Lên lịch nâng cấp dịch vụ và tự động cảnh báo các tenant"
            :icon="Calendar"
        />

        <div class="grid gap-6 lg:grid-cols-[1.2fr,0.8fr]">
            <!-- Schedules List -->
            <div class="flex flex-col gap-6">
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardHeader class="border-b border-border/40 bg-muted/10 p-5">
                        <CardTitle class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <Clock class="size-4.5 text-orange-500" /> Danh sách lịch bảo trì
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5 space-y-4">
                        <div v-for="sch in schedules" :key="sch.id" class="rounded-2xl border border-border/30 p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-muted/15 relative group hover:shadow-xs transition-all duration-300">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2.5 flex-wrap">
                                    <h3 class="font-bold text-xs text-slate-800 dark:text-slate-200">{{ sch.title }}</h3>
                                    <span :class="[
                                        'rounded-full border px-2.5 py-0.5 text-[10px] font-bold shadow-xs', 
                                        sch.status === 'scheduled' ? 'bg-sky-500/10 text-sky-650 border-sky-500/20' :
                                        sch.status === 'active' ? 'bg-rose-500/10 text-rose-600 border-rose-500/20 animate-pulse' :
                                        sch.status === 'completed' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' :
                                        'bg-slate-500/10 text-slate-500 border-slate-500/20'
                                    ]">
                                        {{ statusLabels[sch.status] ?? sch.status }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-[10px] text-muted-foreground font-semibold">
                                    <span class="flex items-center gap-1"><Calendar class="size-3.5" /> Bắt đầu: {{ sch.downtime_start }}</span>
                                    <span class="flex items-center gap-1"><Clock class="size-3.5" /> Kết thúc: {{ sch.downtime_end }}</span>
                                </div>
                                <div class="text-[10px] text-muted-foreground font-semibold">
                                    Dịch vụ ảnh hưởng: 
                                    <span v-for="serv in sch.services" :key="serv" class="inline-block bg-background dark:bg-slate-900 border border-border/40 rounded-lg px-2 py-0.5 text-[9px] font-mono font-bold mr-1 text-slate-600 dark:text-slate-400">
                                        {{ serv === 'mysql' ? 'Hệ thống chính (MySQL)' : serv }}
                                    </span>
                                </div>
                                <p v-if="sch.banner_message" class="text-xs text-slate-700 dark:text-slate-300 border-l-2 pl-2 border-orange-500/50 italic font-medium leading-relaxed">{{ sch.banner_message }}</p>
                                <div class="flex gap-2 text-[9px] text-muted-foreground font-semibold flex-wrap">
                                    <span :class="[sch.notified_24h ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-slate-500/10 text-slate-400 border-slate-500/20']" class="rounded px-2 py-0.5 border font-bold">📧 Báo trước 24h</span>
                                    <span :class="[sch.notified_1h ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-slate-500/10 text-slate-400 border-slate-500/20']" class="rounded px-2 py-0.5 border font-bold">📧 Báo trước 1h</span>
                                </div>
                            </div>
                            <Button v-if="sch.status === 'scheduled' || sch.status === 'active'" @click="cancelSchedule(sch.id)" variant="ghost" size="icon" class="size-8 rounded-lg text-rose-500 hover:text-rose-600 hover:bg-rose-500/10 shrink-0 cursor-pointer">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                        <p v-if="!schedules.length" class="py-12 text-center text-xs font-bold text-muted-foreground">Chưa có lịch bảo trì nào được đăng ký.</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Create Form & AI Advisor -->
            <div class="flex flex-col gap-6">
                <!-- Form Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardHeader class="border-b border-border/40 bg-muted/10 p-5">
                        <CardTitle class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-2">
                            <Wrench class="size-5 text-orange-500" /> Lên lịch bảo trì mới
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <form @submit.prevent="submit" class="space-y-4">
                            <div class="grid gap-1.5">
                                <Label for="title" class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                                    Tên chiến dịch bảo trì
                                </Label>
                                <div class="relative flex items-center">
                                    <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                        <Wrench class="size-4 text-orange-500" />
                                    </div>
                                    <Input id="title" v-model="form.title" placeholder="Bảo trì nâng cấp Database Q3..." class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" required />
                                </div>
                                <span v-if="form.errors.title" class="text-xs text-rose-500 font-semibold">{{ form.errors.title }}</span>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="downtime_start" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Thời gian bắt đầu</Label>
                                <div class="relative flex items-center">
                                    <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                        <Calendar class="size-4 text-orange-500" />
                                    </div>
                                    <Input id="downtime_start" type="datetime-local" v-model="form.downtime_start" class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" required />
                                </div>
                                <span v-if="form.errors.downtime_start" class="text-xs text-rose-500 font-semibold">{{ form.errors.downtime_start }}</span>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="downtime_end" class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Thời gian kết thúc (dự kiến)</Label>
                                <div class="relative flex items-center">
                                    <div class="absolute left-3 text-muted-foreground pointer-events-none">
                                        <Calendar class="size-4 text-orange-500" />
                                    </div>
                                    <Input id="downtime_end" type="datetime-local" v-model="form.downtime_end" class="pl-9.5 rounded-xl border-border bg-background text-xs h-9 focus-visible:ring-orange-500/20 focus-visible:border-orange-500 focus:ring-orange-500/20 focus:border-orange-500 font-semibold" required />
                                </div>
                                <span v-if="form.errors.downtime_end" class="text-xs text-rose-500 font-semibold">{{ form.errors.downtime_end }}</span>
                            </div>

                            <div class="space-y-2">
                                <Label class="text-xs font-bold text-muted-foreground uppercase tracking-wider">Các dịch vụ bị khóa</Label>
                                <div class="grid grid-cols-1 gap-2.5">
                                    <div v-for="serv in services" :key="serv.service_key"
                                         @click="toggleService(serv.service_key)"
                                         class="flex items-center justify-between p-3 rounded-xl border transition-all duration-200 cursor-pointer"
                                         :class="form.services.includes(serv.service_key) ? 'border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/10' : 'border-border/60 hover:bg-muted/30'">
                                        <div class="flex items-center gap-2.5">
                                            <Server class="size-4 text-orange-500 shrink-0" />
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ serv.name }}</span>
                                                <span class="text-[9px] text-muted-foreground font-semibold mt-0.5">Khóa dịch vụ {{ serv.service_key }} khi đến giờ bảo trì.</span>
                                            </div>
                                        </div>
                                        <div :class="form.services.includes(serv.service_key) ? 'bg-orange-500 text-white border-transparent' : 'border border-border/70 bg-transparent'"
                                             class="size-5 rounded-full flex items-center justify-center transition-all duration-200 shrink-0">
                                             <Check v-if="form.services.includes(serv.service_key)" class="size-3" />
                                        </div>
                                    </div>
                                </div>
                                <span v-if="form.errors.services" class="text-xs text-rose-500 font-semibold">{{ form.errors.services }}</span>
                            </div>

                            <div class="grid gap-1.5">
                                <Label for="banner_message" class="text-xs font-bold text-muted-foreground uppercase tracking-wider flex items-center gap-1.5">
                                    Thông báo hiển thị cho tenant
                                </Label>
                                <div class="relative">
                                    <textarea
                                        id="banner_message"
                                        v-model="form.banner_message"
                                        placeholder="Hệ thống sẽ bảo trì nâng cấp từ 02:00 - 04:00 ngày 25/06..."
                                        class="min-h-[80px] w-full rounded-xl border border-border bg-background pl-9.5 pr-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 font-semibold leading-relaxed"
                                        required
                                    ></textarea>
                                    <div class="absolute left-3 top-2.5 text-muted-foreground pointer-events-none">
                                        <Bell class="size-4 text-orange-500" />
                                    </div>
                                </div>
                                <span v-if="form.errors.banner_message" class="text-xs text-rose-500 font-semibold">{{ form.errors.banner_message }}</span>
                            </div>

                            <Button type="submit" :disabled="form.processing" class="w-full bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white rounded-xl shadow-xs cursor-pointer font-bold h-10 text-xs transition-colors flex items-center justify-center gap-2">
                                <span>Đăng ký Lịch bảo trì</span>
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- AI Maintenance Advisor Card -->
                <Card class="border border-border/40 bg-card/45 backdrop-blur-md shadow-2xs overflow-hidden rounded-2xl">
                    <CardContent class="p-5 space-y-4">
                        <div class="flex items-center justify-between border-b border-border/40 pb-3">
                            <h4 class="text-xs font-black text-muted-foreground uppercase tracking-wider">AI Cảnh báo & Advisor</h4>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase border animate-pulse"
                                :class="
                                    hasMaintenanceWarnings ? 'bg-amber-500/10 text-amber-600 border-amber-500/20' : 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20'
                                "
                            >
                                {{ hasMaintenanceWarnings ? 'Khuyến nghị' : 'Sẵn sàng' }}
                            </span>
                        </div>

                        <div class="space-y-3">
                            <!-- Hour Advice -->
                            <div class="p-3 rounded-xl border flex items-start gap-2.5"
                                :class="downtimeAdvice.status === 'warning' ? 'border-amber-500/20 bg-amber-500/[0.03]' : 'border-border/30 bg-muted/10'"
                            >
                                <div class="p-1 rounded-lg shrink-0 mt-0.5"
                                    :class="downtimeAdvice.status === 'warning' ? 'bg-amber-500/10 text-amber-500' : 'bg-slate-500/10 text-slate-500'"
                                >
                                    <Clock class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Giờ bắt đầu</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">{{ downtimeAdvice.text }}</p>
                                </div>
                            </div>

                            <!-- Duration Advice -->
                            <div class="p-3 rounded-xl border flex items-start gap-2.5"
                                :class="durationAdvice.status === 'warning' ? 'border-amber-500/20 bg-amber-500/[0.03]' : durationAdvice.status === 'error' ? 'border-rose-500/20 bg-rose-500/[0.03]' : 'border-border/30 bg-muted/10'"
                            >
                                <div class="p-1 rounded-lg shrink-0 mt-0.5"
                                    :class="durationAdvice.status === 'warning' ? 'bg-amber-500/10 text-amber-500' : durationAdvice.status === 'error' ? 'bg-rose-500/10 text-rose-500' : 'bg-slate-500/10 text-slate-500'"
                                >
                                    <Calendar class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Thời lượng bảo trì</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">{{ durationAdvice.text }}</p>
                                </div>
                            </div>

                            <!-- Services Advice -->
                            <div class="p-3 rounded-xl border flex items-start gap-2.5"
                                :class="servicesAdvice.status === 'caution' ? 'border-amber-500/20 bg-amber-500/[0.03]' : 'border-border/30 bg-muted/10'"
                            >
                                <div class="p-1 rounded-lg shrink-0 mt-0.5"
                                    :class="servicesAdvice.status === 'caution' ? 'bg-amber-500/10 text-amber-500' : 'bg-slate-500/10 text-slate-500'"
                                >
                                    <Wrench class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Dịch vụ ảnh hưởng</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">{{ servicesAdvice.text }}</p>
                                </div>
                            </div>

                            <!-- Banner message Advice -->
                            <div class="p-3 rounded-xl border flex items-start gap-2.5"
                                :class="bannerAdvice.status === 'caution' ? 'border-amber-500/20 bg-amber-500/[0.03]' : 'border-border/30 bg-muted/10'"
                            >
                                <div class="p-1 rounded-lg shrink-0 mt-0.5"
                                    :class="bannerAdvice.status === 'caution' ? 'bg-amber-500/10 text-amber-500' : 'bg-slate-500/10 text-slate-500'"
                                >
                                    <ShieldAlert class="size-4" />
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Cảnh báo Tenants</p>
                                    <p class="text-[10px] text-muted-foreground leading-normal mt-0.5 font-semibold">{{ bannerAdvice.text }}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
