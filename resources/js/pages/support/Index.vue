<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    Headset, HelpCircle, Calendar, MessageSquare, BookOpen, Compass, Search,
    Plus, Send, CheckCircle2, User, Clock, ChevronRight, Play, RefreshCw, AlertCircle
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

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

// Form táº¡o ticket
const ticketForm = useForm({
    category: 'realtime',
    title: '',
    description: ''
});

// Form gá»­i cÃ¢u tráº£ lá»i
const replyForm = useForm({
    message: ''
});

// Form Ä‘áº·t lá»‹ch demo
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
    open: 'Äang chá» xá»­ lÃ½',
    in_progress: 'Äang xá»­ lÃ½',
    waiting_restaurant: 'Chá» pháº£n há»“i',
    resolved: 'ÄÃ£ giáº£i quyáº¿t',
    closed: 'ÄÃ£ Ä‘Ã³ng'
};

const statusColors: Record<string, string> = {
    open: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-400',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-400',
    waiting_restaurant: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-400',
    resolved: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400',
    closed: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-400',
};

// Lá»c cÃ¡c bÃ i viáº¿t tÃ i liá»‡u
const filteredArticles = computed(() => {
    if (!searchQuery.value) return props.articles;
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
            // Chá»n ticket vá»«a táº¡o
            setTimeout(() => {
                if (props.tickets.length > 0) {
                    selectedTicket.value = props.tickets[0];
                }
            }, 500);
        }
    });
};

const submitReply = (ticketId: number) => {
    if (!replyForm.message.trim()) return;
    replyForm.post(`/support/tickets/${ticketId}/replies`, {
        onSuccess: () => {
            replyForm.reset();
            // Cáº­p nháº­t ticket Ä‘ang chá»n
            const updated = props.tickets.find(t => t.id === ticketId);
            if (updated) {
                selectedTicket.value = updated;
            }
        }
    });
};

// Äáº·t lá»‹ch demo
const availableSlots = [
    '09:00 - 10:00',
    '10:30 - 11:30',
    '14:00 - 15:00',
    '15:30 - 16:30'
];

const submitBooking = () => {
    if (!bookingForm.date || !bookingForm.time_slot) return;
    // MÃ´ phá»ng lÆ°u booking demo thÃ nh cÃ´ng
    isBookingSuccess.value = true;
    selectedDemoDate.value = bookingForm.date;
    selectedTimeSlot.value = bookingForm.time_slot;
    bookingForm.reset();
};

// KÃ­ch hoáº¡t láº¡i guided tour thá»§ cÃ´ng
const resetOnboarding = () => {
    router.post('/api/onboarding/reset', {}, {
        onSuccess: () => {
            // Chuyá»ƒn vá» dashboard Ä‘á»ƒ báº¯t Ä‘áº§u tour
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

// TÃ­nh toÃ¡n pháº§n trÄƒm tiáº¿n Ä‘á»™ onboarding
const onboardingProgress = computed(() => {
    const userObj = router.page.props.auth.user as any;
    const status = userObj?.onboarding_status;
    if (!status) return 0;
    
    let completedDays = 0;
    if (status.day_1?.completed_at) completedDays++;
    if (status.day_2?.completed_at) completedDays++;
    if (status.day_3?.completed_at) completedDays++;
    
    return Math.round((completedDays / 3) * 100);
});
</script>

<template>
    <Head title="LiÃªn há»‡ & Há»— trá»£" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                    <Headset class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Trung TÃ¢m Há»— Trá»£ & Váº­n HÃ nh</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Äáº·t lá»‹ch Demo 1-on-1 Â· Gá»­i ticket há»— trá»£ Â· TÃ i liá»‡u thÃ´ng minh Â· Guided Tours
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Panel: Guided Tours controls & Announcement -->
            <div class="flex flex-col gap-6 lg:col-span-1">
                <!-- Guided Tours Panel -->
                <Card class="border-indigo-100/50 bg-gradient-to-br from-indigo-50/40 to-white dark:from-slate-900/50 dark:to-slate-900 shadow-sm overflow-hidden">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-md flex items-center gap-2">
                            <Compass class="size-5 text-indigo-600 dark:text-indigo-400 animate-spin-slow" />
                            ÄÃ o Táº¡o & HÆ°á»›ng Dáº«n TÆ°Æ¡ng TÃ¡c
                        </CardTitle>
                        <CardDescription>Tráº£i nghiá»‡m há»‡ thá»‘ng hÆ°á»›ng dáº«n chuáº©n hÃ³a váº­n hÃ nh F&B</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-4">
                        <!-- Progress bar -->
                        <div class="bg-slate-100 dark:bg-slate-800 p-4 rounded-xl">
                            <div class="flex justify-between items-center text-xs font-semibold mb-2">
                                <span class="text-slate-600 dark:text-slate-300">Tiáº¿n trÃ¬nh chuáº©n hÃ³a</span>
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
                                        <p class="text-xs font-bold">NgÃ y 1: BÆ°á»›c chÃ¢n Ä‘áº§u tiÃªn</p>
                                        <p class="text-[11px] text-slate-500">Táº¡o nhÃ³m thá»±c Ä‘Æ¡n & thÃªm mÃ³n má»›i</p>
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
                                        <p class="text-xs font-bold">NgÃ y 2: Chuáº©n hÃ³a váº­n hÃ nh</p>
                                        <p class="text-[11px] text-slate-500">Cáº¥u hÃ¬nh Ä‘á»‹nh lÆ°á»£ng & trá»« kho tá»± Ä‘á»™ng</p>
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
                                        <p class="text-xs font-bold">NgÃ y 3: Quáº£n trá»‹ nhÃ¢n sá»±</p>
                                        <p class="text-[11px] text-slate-500">ThÃªm nhÃ¢n viÃªn & xáº¿p lá»‹ch lÃ m viá»‡c</p>
                                    </div>
                                </div>
                                <Play class="size-4 text-slate-400 group-hover:text-indigo-600 group-hover:translate-x-0.5 transition-all" />
                            </button>
                        </div>

                        <!-- Reset button -->
                        <Button variant="outline" size="sm" @click="resetOnboarding" class="w-full text-slate-600 dark:text-slate-300 font-medium">
                            <RefreshCw class="size-4 mr-2" /> Reset & Báº¯t Ä‘áº§u láº¡i toÃ n bá»™
                        </Button>
                    </CardContent>
                </Card>

                <!-- System Announcements -->
                <Card>
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            <Clock class="size-4 text-rose-500" />
                            ThÃ´ng BÃ¡o Há»‡ Thá»‘ng
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3">
                        <div v-if="announcements.length" class="flex flex-col gap-3">
                            <div v-for="a in announcements" :key="a.id" class="p-3 bg-slate-50 dark:bg-slate-900 border rounded-xl flex flex-col gap-1">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-200">{{ a.title }}</span>
                                    <span v-if="a.level === 'warning'" class="text-[10px] bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded-full border border-amber-200">Quan trá»ng</span>
                                    <span v-else class="text-[10px] bg-sky-50 text-sky-700 px-1.5 py-0.5 rounded-full border border-sky-200">Tin tá»©c</span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">{{ a.message }}</p>
                            </div>
                        </div>
                        <div v-else class="text-center py-6 text-slate-400 text-xs font-normal">
                            KhÃ´ng cÃ³ thÃ´ng bÃ¡o má»›i nÃ o.
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right Panel: Support Tickets, Booking Demo, Documentation -->
            <div class="lg:col-span-2">
                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-3 mb-6 bg-slate-100/60 dark:bg-slate-950 p-1 rounded-xl">
                        <TabsTrigger value="tickets" class="gap-1.5 py-2.5 rounded-lg text-xs font-semibold">
                            <MessageSquare class="size-4" /> Cá»•ng Ticket
                        </TabsTrigger>
                        <TabsTrigger value="booking" class="gap-1.5 py-2.5 rounded-lg text-xs font-semibold">
                            <Calendar class="size-4" /> Äáº·t ca Demo
                        </TabsTrigger>
                        <TabsTrigger value="docs" class="gap-1.5 py-2.5 rounded-lg text-xs font-semibold">
                            <BookOpen class="size-4" /> HÆ°á»›ng Dáº«n ThÃ´ng Minh
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
                                    <Plus class="size-4 text-indigo-600" /> Táº¡o yÃªu cáº§u há»— trá»£ má»›i
                                </CardTitle>
                                <CardDescription>Gáº·p sá»± cá»‘ váº­n hÃ nh? HÃ£y gá»­i ticket cho Ä‘á»™i DevOps xá»­ lÃ½ ngay láº­p tá»©c.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form @submit.prevent="submitTicket" class="space-y-4">
                                    <div class="grid gap-1.5">
                                        <Label for="category">Danh má»¥c sá»± cá»‘</Label>
                                        <Select v-model="ticketForm.category">
                                            <SelectTrigger><SelectValue placeholder="Chá»n danh má»¥c" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="realtime">MÃ n hÃ¬nh Báº¿p / ÄÆ¡n hÃ ng realtime</SelectItem>
                                                <SelectItem value="inventory">Trá»« kho / Äá»‹nh lÆ°á»£ng cÃ´ng thá»©c</SelectItem>
                                                <SelectItem value="billing">HÃ³a Ä‘Æ¡n / GÃ³i Ä‘Äƒng kÃ½ dá»‹ch vá»¥</SelectItem>
                                                <SelectItem value="ui">Lá»—i giao diá»‡n / KhÃ´ng hiá»ƒn thá»‹</SelectItem>
                                                <SelectItem value="other">CÃ¡c váº¥n Ä‘á» khÃ¡c</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="title">TiÃªu Ä‘á» yÃªu cáº§u</Label>
                                        <Input id="title" v-model="ticketForm.title" placeholder="VÃ­ dá»¥: ÄÆ¡n hÃ ng má»›i tá»« QR Table khÃ´ng hiá»ƒn thá»‹ trÃªn Báº¿p" />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="description">MÃ´ táº£ chi tiáº¿t sá»± cá»‘</Label>
                                        <textarea
                                            id="description"
                                            v-model="ticketForm.description"
                                            rows="4"
                                            placeholder="Vui lÃ²ng cung cáº¥p chi tiáº¿t lá»—i, cÃ¡c bÆ°á»›c tÃ¡i hiá»‡n lá»—i Ä‘á»ƒ chÃºng tÃ´i sá»­a nhanh nháº¥t cÃ³ thá»ƒ..."
                                            class="min-h-24 w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                        />
                                    </div>
                                    <div class="flex items-center gap-2 justify-end pt-2">
                                        <Button type="button" variant="outline" @click="showCreateTicket = false">Há»§y</Button>
                                        <Button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white" :disabled="ticketForm.processing">
                                            {{ ticketForm.processing ? 'Äang gá»­i...' : 'Gá»­i yÃªu cáº§u' }}
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
                                    <h3 class="text-sm font-bold">YÃªu cáº§u cá»§a báº¡n</h3>
                                    <Button size="sm" class="h-8 bg-indigo-600 hover:bg-indigo-700 text-white gap-1 text-[11px] rounded-lg" @click="showCreateTicket = true">
                                        <Plus class="size-3.5" /> Gá»­i yÃªu cáº§u
                                    </Button>
                                </div>

                                <div v-if="tickets.length" class="flex flex-col gap-2 max-h-[550px] overflow-y-auto pr-1">
                                    <button
                                        v-for="t in tickets"
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
                                            <span>{{ t.created_at }}</span>
                                            <span class="text-[9px] px-1.5 py-0.5 rounded-md border uppercase font-semibold" :class="severityColors[t.severity]">
                                                {{ t.severity }}
                                            </span>
                                        </div>
                                    </button>
                                </div>
                                <div v-else class="border border-dashed rounded-2xl flex flex-col items-center justify-center p-8 text-center text-slate-400">
                                    <HelpCircle class="size-8 text-slate-300 mb-2" />
                                    <p class="text-xs font-medium">Báº¡n chÆ°a táº¡o yÃªu cáº§u há»— trá»£ nÃ o.</p>
                                    <p class="text-[10px] mt-1">Há»‡ thá»‘ng hoáº¡t Ä‘á»™ng á»•n Ä‘á»‹nh.</p>
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
                                            <strong>Sá»± cá»‘:</strong> {{ selectedTicket.description }}
                                        </div>
                                    </CardHeader>

                                    <!-- Message Thread -->
                                    <CardContent class="flex-1 overflow-y-auto p-4 flex flex-col gap-3.5 max-h-[350px] min-h-[220px]">
                                        <!-- Original issue description -->
                                        <div class="flex items-start gap-3">
                                            <div class="size-8 rounded-full bg-slate-100 flex items-center justify-center font-bold text-xs">U</div>
                                            <div class="bg-slate-100/70 p-3 rounded-2xl text-xs max-w-[85%] text-slate-700">
                                                <p class="font-bold text-[10px] text-slate-500 mb-1">Chá»§ QuÃ¡n</p>
                                                {{ selectedTicket.description }}
                                                <p class="text-[9px] text-slate-400 text-right mt-1.5">{{ selectedTicket.created_at }}</p>
                                            </div>
                                        </div>

                                        <!-- Replies -->
                                        <div v-for="reply in selectedTicket.replies" :key="reply.id" class="flex items-start gap-3" :class="reply.is_staff ? 'flex-row-reverse' : ''">
                                            <div class="size-8 rounded-full flex items-center justify-center font-bold text-xs" :class="reply.is_staff ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100'">
                                                {{ reply.is_staff ? 'AD' : 'U' }}
                                            </div>
                                            <div class="p-3 rounded-2xl text-xs max-w-[85%] text-left" :class="reply.is_staff ? 'bg-indigo-600 text-white' : 'bg-slate-100/70 text-slate-700'">
                                                <p class="font-bold text-[10px] mb-1 text-slate-500" :class="reply.is_staff ? 'text-indigo-200' : ''">
                                                    {{ reply.is_staff ? 'DevOps Engineer' : 'Chá»§ QuÃ¡n' }}
                                                </p>
                                                {{ reply.message }}
                                                <p class="text-[9px] text-right mt-1.5 text-slate-400" :class="reply.is_staff ? 'text-indigo-200/80' : ''">
                                                    {{ reply.created_at }}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>

                                    <!-- Reply Footer -->
                                    <div class="p-3 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
                                        <form @submit.prevent="submitReply(selectedTicket.id)" class="flex gap-2 items-center">
                                            <Input
                                                v-model="replyForm.message"
                                                placeholder="Nháº­p tin nháº¯n pháº£n há»“i..."
                                                class="text-xs h-9 rounded-xl flex-1 bg-white"
                                            />
                                            <Button type="submit" size="sm" class="h-9 w-9 p-0 bg-indigo-600 text-white rounded-xl" :disabled="!replyForm.message.trim()">
                                                <Send class="size-4" />
                                            </Button>
                                        </form>
                                    </div>
                                </Card>
                                <div v-else class="h-full border border-dashed rounded-3xl flex flex-col items-center justify-center p-8 text-center text-slate-400 bg-slate-50/20">
                                    <MessageSquare class="size-10 text-slate-200 mb-2 animate-pulse" />
                                    <p class="text-xs font-semibold">Vui lÃ²ng chá»n hoáº·c táº¡o yÃªu cáº§u há»— trá»£ Ä‘á»ƒ tháº£o luáº­n</p>
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
                                    Äáº·t Lá»‹ch Háº¹n Demo 1-on-1
                                </CardTitle>
                                <CardDescription>Gáº·p khÃ³ khÄƒn trong quÃ¡ trÃ¬nh thiáº¿t láº­p? Äáº·t lá»‹ch gá»i 1-on-1 trá»±c tiáº¿p (30 phÃºt) vá»›i Ä‘á»™i ngÅ© phÃ¡t triá»ƒn F&BViet Ä‘á»ƒ Ä‘Æ°á»£c thiáº¿t láº­p miá»…n phÃ­.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <!-- Booking Success State -->
                                <div v-if="isBookingSuccess" class="border border-emerald-200 bg-emerald-50 dark:bg-emerald-950/20 p-6 rounded-2xl flex flex-col items-center text-center gap-4 py-8 animate-in fade-in duration-300">
                                    <div class="size-12 rounded-full bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 flex items-center justify-center">
                                        <CheckCircle2 class="size-7" />
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-800 dark:text-slate-100">ÄÃ£ Ä‘áº·t lá»‹ch thÃ nh cÃ´ng! ðŸŽ‰</h4>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                                            Thá»i gian: <strong class="text-indigo-600 dark:text-indigo-400">{{ selectedTimeSlot }}</strong> vÃ o ngÃ y <strong>{{ selectedDemoDate }}</strong>
                                        </p>
                                        <p class="text-[11px] text-slate-500 mt-1">Äá»™i ngÅ© ká»¹ thuáº­t cá»§a chÃºng tÃ´i sáº½ gá»i Ä‘iá»‡n thoáº¡i xÃ¡c nháº­n vÃ  gá»­i liÃªn káº¿t Zalo/Google Meet trÆ°á»›c 15 phÃºt diá»…n ra.</p>
                                    </div>
                                    <Button variant="outline" size="sm" @click="isBookingSuccess = false" class="mt-2 text-xs">Äáº·t lá»‹ch ca khÃ¡c</Button>
                                </div>

                                <!-- Booking Form -->
                                <form v-else @submit.prevent="submitBooking" class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="grid gap-1.5">
                                            <Label for="booking-date">Chá»n NgÃ y Háº¹n</Label>
                                            <Input id="booking-date" type="date" v-model="bookingForm.date" :min="new Date().toISOString().split('T')[0]" required />
                                        </div>
                                        <div class="grid gap-1.5">
                                            <Label for="booking-time">Chá»n Khung Giá»</Label>
                                            <Select v-model="bookingForm.time_slot">
                                                <SelectTrigger id="booking-time"><SelectValue placeholder="Chá»n ca ráº£nh" /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="slot in availableSlots" :key="slot" :value="slot">{{ slot }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="booking-phone">Sá»‘ Ä‘iá»‡n thoáº¡i liÃªn há»‡</Label>
                                        <Input id="booking-phone" v-model="bookingForm.phone" placeholder="Nháº­p sá»‘ Ä‘iá»‡n thoáº¡i Ä‘á»ƒ ká»¹ sÆ° liÃªn láº¡c..." required />
                                    </div>
                                    <div class="grid gap-1.5">
                                        <Label for="booking-notes">YÃªu cáº§u Ä‘áº·c biá»‡t hoáº·c MÃ´ táº£ mÃ´ hÃ¬nh nhÃ  hÃ ng</Label>
                                        <textarea
                                            id="booking-notes"
                                            v-model="bookingForm.notes"
                                            rows="3"
                                            placeholder="NÃªu rÃµ mÃ´ hÃ¬nh (VÃ­ dá»¥: quÃ¡n cafe, quÃ¡n phá»Ÿ, nhÃ  hÃ ng láº©u) vÃ  nhá»¯ng tháº¯c máº¯c cáº§n giáº£i Ä‘Ã¡p Ä‘á»ƒ ká»¹ sÆ° chuáº©n bá»‹..."
                                            class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                                        />
                                    </div>
                                    <Button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5">
                                        XÃ¡c nháº­n ÄÄƒng kÃ½ Demo
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
                                    Cáº©m Nang HÆ°á»›ng Dáº«n ThÃ´ng Minh
                                </CardTitle>
                                <CardDescription>Giáº£i phÃ¡p tá»± váº­n hÃ nh nhÃ  hÃ ng chuáº©n hÃ³a, tá»« thá»±c Ä‘Æ¡n Ä‘áº¿n kho bÃ£i.</CardDescription>
                                <div class="relative w-full mt-3">
                                    <Search class="absolute left-3 top-2.5 size-4 text-slate-400" />
                                    <Input
                                        v-model="searchQuery"
                                        placeholder="TÃ¬m bÃ i viáº¿t hÆ°á»›ng dáº«n (vÃ­ dá»¥: cÃ´ng thá»©c Ä‘á»‹nh lÆ°á»£ng, táº¡o mÃ³n, thÃªm nhÃ¢n viÃªn)..."
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
                                                {{ art.summary ?? 'KhÃ´ng cÃ³ tÃ³m táº¯t.' }}
                                            </p>
                                        </div>

                                        <div class="flex justify-between items-center mt-4 pt-2 border-t text-[10px] text-slate-400">
                                            <span class="flex items-center gap-1">LÆ°á»£t xem: {{ art.view_count }}</span>
                                            <a
                                                v-if="art.video_url"
                                                :href="art.video_url"
                                                target="_blank"
                                                class="flex items-center gap-1 font-semibold text-rose-600 hover:underline"
                                            >
                                                Xem Video â–¶
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="text-center py-12 text-slate-400">
                                    <AlertCircle class="size-8 text-slate-300 mx-auto mb-2 animate-bounce" />
                                    <p class="text-xs">KhÃ´ng tÃ¬m tháº¥y tÃ i liá»‡u phÃ¹ há»£p.</p>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    </div>
</template>
