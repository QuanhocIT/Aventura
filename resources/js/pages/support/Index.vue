<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import {
    Headset, HelpCircle, Calendar, MessageSquare, BookOpen, Compass, Search,
    Plus, Send, CheckCircle2, User, Clock, ChevronRight, Play, RefreshCw, AlertCircle
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type TicketReply = { id: number; user_name: string; is_staff: boolean; message: string; created_at: string };
type SupportTicket = { id: number; code: string; title: string; description: string; category: string; severity: string; priority: string; status: string; created_at: string; replies: TicketReply[] };

const props = defineProps<{
    tickets: SupportTicket[];
    articles: Array<Record<string, any>>;
    announcements: Array<Record<string, any>>;
}>();

const activeTab = ref('tickets');
const selectedTicket = ref<SupportTicket | null>(props.tickets[0] ?? null);
const searchQuery = ref('');
const showCreateTicket = ref(false);
const ticketStatusFilter = ref<string>('all');

const filteredTickets = computed(() => {
    if (ticketStatusFilter.value === 'all') {
return props.tickets;
}

    return props.tickets.filter(t => t.status === ticketStatusFilter.value);
});

// Form tạo ticket
const ticketForm = useForm({
    category: 'realtime',
    title: '',
    description: ''
});

// Form gửi câu trả lời
const replyForm = useForm({
    message: ''
});

// Form đặt lịch demo
const bookingForm = useForm({
    date: '',
    time_slot: '',
    phone: '',
    notes: ''
});
const isBookingSuccess = ref(false);
const selectedDemoDate = ref('');
const selectedTimeSlot = ref('');

const severityColors: Record<string, string> = {
    critical: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/20 dark:text-red-400 dark:border-red-900',
    high: 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/20 dark:text-orange-400 dark:border-orange-900',
    medium: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900',
    low: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900',
};

const statusLabels: Record<string, string> = {
    open: 'Đang chờ xử lý',
    in_progress: 'Đang xử lý',
    waiting_restaurant: 'Chờ phản hồi',
    resolved: 'Đã giải quyết',
    closed: 'Đã đóng'
};

const statusColors: Record<string, string> = {
    open: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-400',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-400',
    waiting_restaurant: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400',
    resolved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400',
    closed: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-400',
};

// Lọc các bài viết tài liệu
const filteredArticles = computed(() => {
    if (!searchQuery.value) {
return props.articles;
}

    const query = searchQuery.value.toLowerCase();

    return props.articles.filter(
        art => art.title.toLowerCase().includes(query) || art.summary?.toLowerCase().includes(query)
    );
});

const submitTicket = () => {
    ticketForm.post('/support/tickets', {
        onSuccess: () => {
            ticketForm.reset();
            showCreateTicket.value = false;
            // Chọn ticket vừa tạo
            setTimeout(() => {
                if (props.tickets.length > 0) {
                    selectedTicket.value = props.tickets[0];
                }
            }, 500);
        }
    });
};

const submitReply = (ticketId: number) => {
    if (!replyForm.message.trim()) {
return;
}

    replyForm.post(`/support/tickets/${ticketId}/replies`, {
        onSuccess: () => {
            replyForm.reset();
            // Cập nhật ticket đang chọn
            const updated = props.tickets.find(t => t.id === ticketId);

            if (updated) {
                selectedTicket.value = updated;
            }
        }
    });
};

// Đặt lịch demo
const availableSlots = [
    '09:00 - 10:00',
    '10:30 - 11:30',
    '14:00 - 15:00',
    '15:30 - 16:30'
];

const submitBooking = () => {
    if (!bookingForm.date || !bookingForm.time_slot) {
return;
}

    selectedDemoDate.value = bookingForm.date;
    selectedTimeSlot.value = bookingForm.time_slot;
    bookingForm.post('/support/bookings', {
        onSuccess: () => {
            isBookingSuccess.value = true;
            bookingForm.reset();
        }
    });
};

// Kích hoạt lại guided tour thủ công
const resetOnboarding = () => {
    router.post('/api/onboarding/reset', {}, {
        onSuccess: () => {
            // Chuyển về dashboard để bắt đầu tour
            router.visit('/dashboard');
        }
    });
};

const triggerTour = (day: number) => {
    router.post('/api/onboarding/update', {
        current_day: day
    }, {
        onSuccess: () => {
            const dests: Record<number, string> = {
                1: '/products',
                2: '/inventory',
                3: '/employees'
            };
            router.visit(dests[day]);
        }
    });
};

function formatRelativeTime(dateStr: string): string {
    const date = new Date(dateStr);

    if (isNaN(date.getTime())) {
return dateStr;
}

    const diff = Math.floor((Date.now() - date.getTime()) / 1000);

    if (diff < 60)  {
return 'Vừa xong';
}

    if (diff < 3600) {
return `${Math.floor(diff / 60)} phút trước`;
}

    if (diff < 86400) {
return `${Math.floor(diff / 3600)} giờ trước`;
}

    if (diff < 2592000) {
return `${Math.floor(diff / 86400)} ngày trước`;
}

    return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Tính toán phần trăm tiến độ onboarding
const onboardingProgress = computed(() => {
    const page = usePage();
    const userObj = page.props.auth?.user as any;
    const status = userObj?.onboarding_status;

    if (!status) {
return 0;
}
    
    let completedDays = 0;

    if (status.day_1?.completed_at) {
completedDays++;
}

    if (status.day_2?.completed_at) {
completedDays++;
}

    if (status.day_3?.completed_at) {
completedDays++;
}
    
    return Math.round((completedDays / 3) * 100);
});

const isOwner = computed(() => {
    const page = usePage();
    const roles = page.props.roles ?? [];
    return Array.isArray(roles) ? roles.includes('owner') : false;
});
</script>

<template>
    <Head title="Liên hệ & Hỗ trợ" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Headset class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Trung Tâm Hỗ Trợ & Vận Hành</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Đặt lịch Demo 1-on-1 · Gửi ticket hỗ trợ · Tài liệu thông minh · Guided Tours
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Panel: Guided Tours controls & Announcement -->
            <div class="flex flex-col gap-6 lg:col-span-1">
                <!-- Guided Tours Panel -->
                <Card v-if="isOwner" class="border-indigo-100/50 bg-gradient-to-br from-indigo-50/40 to-white dark:from-slate-900/50 dark:to-slate-900 shadow-sm overflow-hidden">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-md flex items-center gap-2">
                            <Compass class="size-5 text-indigo-600 dark:text-indigo-400 animate-spin-slow" />
                            Đào Tạo & Hướng Dẫn Tương Tác
                        </CardTitle>
                        <CardDescription>Trải nghiệm hệ thống hướng dẫn chuẩn hóa vận hành F&B</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <!-- Progress bar -->
                        <div class="bg-slate-100 dark:bg-slate-800 p-4 rounded-xl">
                            <div class="flex justify-between items-center text-xs font-semibold mb-2">
                                <span class="text-slate-600 dark:text-slate-300">Tiến trình chuẩn hóa</span>
                                <span class="text-indigo-600 dark:text-indigo-400 font-mono">{{ onboardingProgress }}%</span>
                            </div>
                            <div class="w-full bg-slate-200 dark:bg-slate-700 h-2 rounded-full overflow-hidden">
                                <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" :style="{ width: `${onboardingProgress}%` }" />
                            </div>
                        </div>

                        <!-- 3-Day Steps Grid -->
                        <div class="flex flex-col gap-2">
                            <button
                                @click="triggerTour(1)"
                                class="flex items-center justify-between p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-indigo-500 hover:shadow-md transition-all text-left group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center font-bold text-sm text-indigo-600 dark:text-indigo-400">1</div>
                                    <div>
                                        <p class="text-xs font-bold">Ngày 1: Bước chân đầu tiên</p>
                                        <p class="text-[11px] text-slate-500">Tạo nhóm thực đơn & thêm món mới</p>
                                    </div>
                                </div>
                                <Play class="size-4 text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all" />
                            </button>

                            <button
                                @click="triggerTour(2)"
                                class="flex items-center justify-between p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-indigo-500 hover:shadow-md transition-all text-left group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center font-bold text-sm text-indigo-600 dark:text-indigo-400">2</div>
                                    <div>
                                        <p class="text-xs font-bold">Ngày 2: Chuẩn hóa vận hành</p>
                                        <p class="text-[11px] text-slate-500">Cấu hình định lượng & trừ kho tự động</p>
                                    </div>
                                </div>
                                <Play class="size-4 text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all" />
                            </button>

                            <button
                                @click="triggerTour(3)"
                                class="flex items-center justify-between p-3.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-indigo-500 hover:shadow-md transition-all text-left group"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="size-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center font-bold text-sm text-indigo-600 dark:text-indigo-400">3</div>
                                    <div>
                                        <p class="text-xs font-bold">Ngày 3: Quản trị nhân sự</p>
                                        <p class="text-[11px] text-slate-500">Thêm nhân viên & xếp lịch làm việc</p>
                                    </div>
                                </div>
                                <Play class="size-4 text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all" />
                            </button>
                        </div>

                        <!-- Reset button -->
                        <Button variant="outline" size="sm" @click="resetOnboarding" class="w-full text-slate-600 dark:text-slate-300 font-medium">
                            <RefreshCw class="size-4 mr-2" /> Reset & Bắt đầu lại toàn bộ
                        </Button>
                    </CardContent>
                </Card>

                <!-- System Announcements -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            <Clock class="size-4 text-rose-500" />
                            Thông Báo Hệ Thống
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <div v-if="announcements.length" class="flex flex-col gap-3">
                            <div v-for="a in announcements" :key="a.id" class="p-3 bg-slate-50 dark:bg-slate-900 border rounded-xl flex flex-col gap-1">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ a.title }}</span>
                                    <span v-if="a.level === 'warning'" class="text-[10px] bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded-full border border-amber-200">Quan trọng</span>
                                    <span v-else class="text-[10px] bg-sky-50 text-sky-700 px-1.5 py-0.5 rounded-full border border-sky-200">Tin tức</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">{{ a.message }}</p>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-slate-400 text-xs font-normal">
                            Không có thông báo mới nào.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Panel: Support Tickets, Booking Demo, Documentation -->
            <div class="lg:col-span-2">
                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-3 mb-6 bg-slate-100/60 dark:bg-slate-950 p-1 rounded-xl">
                        <TabsTrigger value="tickets" class="gap-1.5 py-2.5 rounded-lg text-xs font-semibold">
                            <MessageSquare class="size-4" /> Cổng Ticket
                        </TabsTrigger>
                        <TabsTrigger value="booking" class="gap-1.5 py-2.5 rounded-lg text-xs font-semibold">
                            <Calendar class="size-4" /> Đặt ca Demo
                        </TabsTrigger>
                        <TabsTrigger value="docs" class="gap-1.5 py-2.5 rounded-lg text-xs font-semibold">
                            <BookOpen class="size-4" /> Hướng Dẫn Thông Minh
                        </TabsTrigger>
                    </TabsList>

                    <!-- ========================================== -->
                    <!-- TAB: TICKETS SYSTEM                       -->
                    <!-- ========================================== -->
                    <TabsContent value="tickets">
                        <!-- Create Ticket State -->
                        <Card v-if="showCreateTicket" class="shadow-sm">
                            <CardHeader>
                                <CardTitle class="text-base flex items-center gap-2">
                                    <Plus class="size-4 text-indigo-600" /> Tạo yêu cầu hỗ trợ mới
                                </CardTitle>
                                <CardDescription>Gặp sự cố vận hành? Hãy gửi ticket cho đội DevOps xử lý ngay lập tức.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form @submit.prevent="submitTicket" class="space-y-4">
                                    <div class="grid gap-1.5">
                                        <Label for="category">Danh mục sự cố</Label>
                                        <Select v-model="ticketForm.category">
                                            <SelectTrigger><SelectValue placeholder="Chọn danh mục" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="realtime">Màn hình Bếp / Đơn hàng realtime</SelectItem>
                                                <SelectItem value="inventory">Trừ kho / Định lượng công thức</SelectItem>
                                                <SelectItem value="billing">Hóa đơn / Gói đăng ký dịch vụ</SelectItem>
                                                <SelectItem value="ui">Lỗi giao diện / Không hiển thị</SelectItem>
                                                <SelectItem value="other">Các vấn đề khác</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="title">Tiêu đề yêu cầu</Label>
                                        <Input id="title" v-model="ticketForm.title" placeholder="Ví dụ: Đơn hàng mới từ QR Table không hiển thị trên Bếp" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="description">Mô tả chi tiết sự cố</Label>
                                        <textarea
                                            id="description"
                                            v-model="ticketForm.description"
                                            rows="4"
                                            placeholder="Vui lòng cung cấp chi tiết lỗi, các bước tái hiện lỗi để chúng tôi sửa nhanh nhất có thể..."
                                            class="min-h-24 w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                        />
                                    </div>
                                    <div class="flex items-center gap-2 justify-end pt-2">
                                        <Button type="button" variant="outline" @click="showCreateTicket = false">Hủy</Button>
                                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white" :disabled="ticketForm.processing">
                                            {{ ticketForm.processing ? 'Đang gửi...' : 'Gửi yêu cầu' }}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        <!-- Tickets List and Chat Panel -->
                        <div v-else class="grid grid-cols-1 md:grid-cols-5 gap-6 min-h-[500px]">
                            <!-- Tickets Listing -->
                            <div class="md:col-span-2 flex flex-col gap-3">
                                <div class="flex justify-between items-center mb-1">
                                    <h3 class="text-sm font-bold">Yêu cầu của bạn</h3>
                                    <Button size="sm" class="h-8 bg-indigo-600 hover:bg-indigo-700 text-white gap-1 text-[11px] rounded-lg" @click="showCreateTicket = true">
                                        <Plus class="size-3.5" /> Gửi yêu cầu
                                    </Button>
                                </div>

                                <!-- Status filter chips -->
                                <div class="flex flex-wrap gap-1.5 mb-2">
                                    <button
                                        v-for="(label, key) in ({ all: 'Tất cả', open: 'Chờ xử lý', in_progress: 'Đang xử lý', resolved: 'Đã giải quyết', closed: 'Đã đóng' } as Record<string, string>)"
                                        :key="key"
                                        @click="ticketStatusFilter = key"
                                        class="px-2.5 py-1 rounded-full text-[10px] font-semibold border transition-all"
                                        :class="ticketStatusFilter === key
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-400 dark:border-indigo-700'
                                            : 'border-slate-200 bg-white text-slate-500 dark:border-slate-800 dark:bg-slate-950 hover:border-slate-300'"
                                    >
                                        {{ label }}
                                        <span v-if="key !== 'all'" class="ml-1 opacity-60">
                                            {{ tickets.filter(t => t.status === key).length }}
                                        </span>
                                        <span v-else class="ml-1 opacity-60">{{ tickets.length }}</span>
                                    </button>
                                </div>

                                <div v-if="filteredTickets.length" class="flex flex-col gap-2 max-h-[550px] overflow-y-auto pr-1">
                                    <button
                                        v-for="t in filteredTickets"
                                        :key="t.id"
                                        @click="selectedTicket = t"
                                        class="p-4 rounded-xl border text-left flex flex-col gap-2 transition-all hover:bg-slate-50 dark:hover:bg-slate-900"
                                        :class="selectedTicket?.id === t.id ? 'border-indigo-500 bg-indigo-50/20 dark:bg-indigo-950/20 shadow-sm' : 'border-slate-100 bg-white dark:border-slate-800 dark:bg-slate-950'"
                                    >
                                        <div class="flex justify-between items-start gap-2">
                                            <span class="text-[10px] font-mono text-slate-400">{{ t.code }}</span>
                                            <span class="text-[10px] px-2 py-0.5 rounded-full font-medium" :class="statusColors[t.status]">
                                                {{ statusLabels[t.status] }}
                                            </span>
                                        </div>
                                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200 line-clamp-1">{{ t.title }}</p>
                                        <div class="flex justify-between items-center text-[10px] text-slate-400 mt-1">
                                            <span>{{ formatRelativeTime(t.created_at) }}</span>
                                            <span class="text-[9px] px-1.5 py-0.5 rounded-md border uppercase font-semibold" :class="severityColors[t.severity]">
                                                {{ t.severity }}
                                            </span>
                                        </div>
                                    </button>
                                </div>
                                <div v-else class="border border-dashed rounded-2xl flex flex-col items-center justify-center p-8 text-center text-slate-400">
                                    <HelpCircle class="size-8 text-slate-300 mb-2" />
                                    <p class="text-xs font-medium">
                                        {{ ticketStatusFilter === 'all' ? 'Bạn chưa tạo yêu cầu hỗ trợ nào.' : 'Không có ticket nào ở trạng thái này.' }}
                                    </p>
                                    <p class="text-[10px] mt-1">Hệ thống hoạt động ổn định.</p>
                                </div>
                            </div>

                            <!-- Chat/Messages Detail Pane -->
                            <div class="md:col-span-3">
                                <Card v-if="selectedTicket" class="h-full flex flex-col min-h-[500px]">
                                    <!-- Header -->
                                    <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800">
                                        <div class="flex justify-between items-start gap-4">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono text-xs text-slate-400 font-semibold">{{ selectedTicket.code }}</span>
                                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-medium" :class="statusColors[selectedTicket.status]">
                                                        {{ statusLabels[selectedTicket.status] }}
                                                    </span>
                                                </div>
                                                <CardTitle class="text-sm font-bold mt-1.5">{{ selectedTicket.title }}</CardTitle>
                                            </div>
                                        </div>
                                        <div class="mt-2.5 text-xs bg-slate-50 dark:bg-slate-900 border rounded-lg p-3 text-slate-600 dark:text-slate-300">
                                            <strong>Sự cố:</strong> {{ selectedTicket.description }}
                                        </div>
                                    </CardHeader>

                                    <!-- Message Thread -->
                                    <CardContent class="flex-1 overflow-y-auto p-4 flex flex-col gap-3.5 max-h-[350px] min-h-[220px]">
                                        <!-- Original issue description -->
                                        <div class="flex items-start gap-3">
                                            <div class="size-8 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs">U</div>
                                            <div class="bg-slate-100/70 p-3 rounded-2xl text-xs max-w-[85%] text-slate-700">
                                                <p class="font-bold text-[10px] text-slate-500 mb-1">Chủ Quán</p>
                                                {{ selectedTicket.description }}
                                                <p class="text-[9px] text-slate-400 text-right mt-1.5">{{ formatRelativeTime(selectedTicket.created_at) }}</p>
                                            </div>
                                        </div>

                                        <!-- Replies -->
                                        <div v-for="reply in selectedTicket.replies" :key="reply.id" class="flex items-start gap-3" :class="reply.is_staff ? 'flex-row-reverse' : ''">
                                            <div class="size-8 rounded-full flex items-center justify-center font-bold text-xs" :class="reply.is_staff ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100'">
                                                {{ reply.is_staff ? 'AD' : 'U' }}
                                            </div>
                                            <div class="p-3 rounded-2xl text-xs max-w-[85%] text-left" :class="reply.is_staff ? 'bg-indigo-600 text-white' : 'bg-slate-100/70 text-slate-700'">
                                                <p class="font-bold text-[10px] mb-1 text-slate-500" :class="reply.is_staff ? 'text-indigo-200' : ''">
                                                    {{ reply.is_staff ? 'DevOps Engineer' : 'Chủ Quán' }}
                                                </p>
                                                {{ reply.message }}
                                                <p class="text-[9px] text-right mt-1.5 text-slate-400" :class="reply.is_staff ? 'text-indigo-200/80' : ''">
                                                    {{ formatRelativeTime(reply.created_at) }}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>

                                    <!-- Reply Footer -->
                                    <div class="p-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                                        <form @submit.prevent="submitReply(selectedTicket.id)" class="flex gap-2 items-center">
                                            <Input
                                                v-model="replyForm.message"
                                                placeholder="Nhập tin nhắn phản hồi..."
                                                class="text-xs h-9 rounded-xl flex-1 bg-white"
                                            />
                                            <Button type="submit" size="sm" class="h-9 w-9 p-0 bg-indigo-600 text-white rounded-xl" :disabled="!replyForm.message.trim() || replyForm.processing">
                                                <Send class="size-4" />
                                            </Button>
                                        </form>
                                    </div>
                                </Card>
                                <div v-else class="h-full border border-dashed rounded-3xl flex flex-col items-center justify-center p-8 text-center text-slate-400 bg-slate-50/20">
                                    <MessageSquare class="size-10 text-slate-200 mb-2 animate-pulse" />
                                    <p class="text-xs font-semibold">Vui lòng chọn hoặc tạo yêu cầu hỗ trợ để thảo luận</p>
                                </div>
                            </div>
                        </div>
                    </TabsContent>

                    <!-- ========================================== -->
                    <!-- TAB: DIRECT DEMO BOOKING                  -->
                    <!-- ========================================== -->
                    <TabsContent value="booking">
                        <Card>
                            <CardHeader>
                                <CardTitle class="text-base flex items-center gap-2">
                                    <Calendar class="size-5 text-indigo-600" />
                                    Đặt Lịch Hẹn Demo 1-on-1
                                </CardTitle>
                                <CardDescription>Gặp khó khăn trong quá trình thiết lập? Đặt lịch gọi 1-on-1 trực tiếp (30 phút) với đội ngũ phát triển F&BViet để được thiết lập miễn phí.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <!-- Booking Success State -->
                                <div v-if="isBookingSuccess" class="border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/20 p-6 rounded-2xl flex flex-col items-center text-center gap-4 py-8 animate-in fade-in duration-300">
                                    <div class="size-12 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 flex items-center justify-center">
                                        <CheckCircle2 class="size-7" />
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-800 dark:text-slate-100">Đã đặt lịch thành công! 🎉</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                                            Thời gian: <strong class="text-indigo-600 dark:text-indigo-400">{{ selectedTimeSlot }}</strong> vào ngày <strong>{{ selectedDemoDate }}</strong>
                                        </p>
                                        <p class="text-[11px] text-slate-500 mt-1">Đội ngũ kỹ thuật của chúng tôi sẽ gọi điện thoại xác nhận và gửi liên kết Zalo/Google Meet trước 15 phút diễn ra.</p>
                                    </div>
                                    <Button variant="outline" size="sm" @click="isBookingSuccess = false" class="mt-2 text-xs">Đặt lịch ca khác</Button>
                                </div>

                                <!-- Booking Form -->
                                <form v-else @submit.prevent="submitBooking" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="grid gap-1.5">
                                            <Label for="booking-date">Chọn Ngày Hẹn</Label>
                                            <Input id="booking-date" type="date" v-model="bookingForm.date" :min="new Date().toISOString().split('T')[0]" required />
                                        </div>
                                        <div class="grid gap-1.5">
                                            <Label for="booking-time">Chọn Khung Giờ</Label>
                                            <Select v-model="bookingForm.time_slot">
                                                <SelectTrigger id="booking-time"><SelectValue placeholder="Chọn ca rảnh" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="slot in availableSlots" :key="slot" :value="slot">{{ slot }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="booking-phone">Số điện thoại liên hệ</Label>
                                        <Input id="booking-phone" v-model="bookingForm.phone" placeholder="Nhập số điện thoại để kỹ sư liên lạc..." required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="booking-notes">Yêu cầu đặc biệt hoặc Mô tả mô hình nhà hàng</Label>
                                        <textarea
                                            id="booking-notes"
                                            v-model="bookingForm.notes"
                                            rows="3"
                                            placeholder="Nêu rõ mô hình (Ví dụ: quán cafe, quán phở, nhà hàng lẩu) và những thắc mắc cần giải đáp để kỹ sư chuẩn bị..."
                                            class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                        />
                                    </div>
                                    <Button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5" :disabled="bookingForm.processing">
                                        {{ bookingForm.processing ? 'Đang gửi...' : 'Xác nhận Đăng ký Demo' }}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- ========================================== -->
                    <!-- TAB: SMART DOCUMENTATION                  -->
                    <!-- ========================================== -->
                    <TabsContent value="docs">
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-base flex items-center gap-2">
                                    <BookOpen class="size-5 text-indigo-600" />
                                    Cẩm Nang Hướng Dẫn Thông Minh
                                </CardTitle>
                                <CardDescription>Giải pháp tự vận hành nhà hàng chuẩn hóa, từ thực đơn đến kho bãi.</CardDescription>
                                <div class="relative w-full mt-3">
                                    <Search class="absolute left-3 top-2.5 size-4 text-slate-400" />
                                    <Input
                                        v-model="searchQuery"
                                        placeholder="Tìm bài viết hướng dẫn (ví dụ: công thức định lượng, tạo món, thêm nhân viên)..."
                                        class="pl-9 text-xs h-9 rounded-xl"
                                    />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div v-if="filteredArticles.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div
                                        v-for="art in filteredArticles"
                                        :key="art.id"
                                        class="p-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/40 hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-950 transition-all flex flex-col justify-between"
                                    >
                                        <div>
                                            <span class="text-[9px] font-bold uppercase text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full dark:bg-indigo-950 dark:text-indigo-400">
                                                {{ art.category }}
                                            </span>
                                            <h4 class="font-bold text-xs text-slate-800 dark:text-slate-100 mt-2 leading-tight">
                                                {{ art.title }}
                                            </h4>
                                            <p class="text-[11px] text-slate-500 mt-1 line-clamp-3">
                                                {{ art.summary ?? 'Không có tóm tắt.' }}
                                            </p>
                                        </div>

                                        <div class="flex justify-between items-center mt-4 pt-2 border-t text-[10px] text-slate-400">
                                            <span class="flex items-center gap-1">Lượt xem: {{ art.view_count }}</span>
                                            <a
                                                v-if="art.video_url"
                                                :href="art.video_url"
                                                target="_blank"
                                                class="flex items-center gap-1 font-semibold text-rose-600 hover:underline"
                                            >
                                                Xem Video ▶
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-12 text-slate-400">
                                    <AlertCircle class="size-8 text-slate-300 mx-auto mb-2 animate-bounce" />
                                    <p class="text-xs">Không tìm thấy tài liệu phù hợp.</p>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </div>
</template>
