<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import {
    Bot,
    Send,
    Loader2,
    Sparkles,
    TrendingUp,
    BarChart3,
    ShieldAlert,
    Package,
    ClipboardCheck,
    Clock3,
    ArrowLeftRight,
    BadgeDollarSign,
    Users,
    ArrowRight,
    User,
    RotateCcw,
    Building2,
    Target,
    Wallet,
    Trash2,
} from 'lucide-vue-next';
import { ref, computed, onMounted, nextTick } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { advisorHistory, advisorMessage } from '@/routes/chatbot';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    advisorMode?: 'strategic' | 'central_warehouse';
}>();

interface Message {
    id: string;
    role: 'user' | 'bot';
    content: string;
    timestamp: string;
}

const page = usePage();
const user = computed(() => (page.props.auth?.user as any) ?? null);
const isCentralWarehouse = computed(
    () => props.advisorMode === 'central_warehouse',
);
const advisorTitle = computed(() =>
    isCentralWarehouse.value ? 'Trợ lý AI Kho Tổng' : 'Trợ lý AI Chiến lược',
);
const advisorSubtitle = computed(() =>
    isCentralWarehouse.value
        ? 'HỖ TRỢ ĐIỀU HÀNH KHO VẬN & CHUỖI CUNG ỨNG'
        : 'HỆ THỐNG PHÂN TÍCH DOANH THU & QUẢN TRỊ KINH DOANH',
);
const advisorPlaceholder = computed(() =>
    isCentralWarehouse.value
        ? 'Hỏi về tồn khả dụng, đơn cấp phát, SLA, OTIF, FEFO, điều chuyển, giá vốn...'
        : 'Hỏi về doanh thu chuỗi, top món bán chạy, cảnh báo gian lận, dòng tiền, OKR...',
);

const messages = ref<Message[]>([]);
const inputText = ref('');
const isLoading = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);
const sessionId = ref('');

// Kịch bản chiến lược kinh doanh cho Chủ doanh nghiệp / Ban quản trị
const ownerSuggestions = [
    {
        text: 'Doanh thu hôm nay đạt bao nhiêu và so với hôm qua thế nào?',
        category: 'finance',
        icon: TrendingUp,
    },
    {
        text: 'Top món nào bán chạy và đem lại lợi nhuận cao nhất?',
        category: 'sales',
        icon: BarChart3,
    },
    {
        text: 'Có phát hiện gian lận, lệch két tiền hay hủy món bất thường không?',
        category: 'fraud',
        icon: ShieldAlert,
    },
    {
        text: 'So sánh doanh thu và hiệu suất giữa các chi nhánh trong chuỗi?',
        category: 'branches',
        icon: Building2,
    },
    {
        text: 'Dự báo doanh thu ngày mai và xu hướng tuần tới ra sao?',
        category: 'forecast',
        icon: Sparkles,
    },
    {
        text: 'Tình hình dòng tiền và chi phí hoạt động chuỗi thế nào?',
        category: 'finance',
        icon: Wallet,
    },
    {
        text: 'Tiến độ thực hiện mục tiêu doanh số (OKR) hiện tại ra sao?',
        category: 'goals',
        icon: Target,
    },
    {
        text: 'Nguyên vật liệu nào có tỷ lệ hao hụt cao nhất chuỗi cần can thiệp?',
        category: 'waste',
        icon: Trash2,
    },
];

// Kịch bản điều hành vận hành Kho Tổng cho Trưởng kho
const warehouseSuggestions = [
    {
        text: 'Kho Tổng còn đủ tồn khả dụng để đáp ứng các đơn cấp phát đang mở không?',
        category: 'stock',
        icon: Package,
    },
    {
        text: 'Mặt hàng nào sắp thiếu hoặc đã dưới mức đặt hàng lại (Reorder Point)?',
        category: 'stock',
        icon: ClipboardCheck,
    },
    {
        text: 'Đơn cấp phát nào đang trễ hạn hoặc có nguy cơ trễ cam kết SLA?',
        category: 'requests',
        icon: Clock3,
    },
    {
        text: 'Fill rate và OTIF của Kho Tổng trong 30 ngày qua ra sao?',
        category: 'performance',
        icon: TrendingUp,
    },
    {
        text: 'Lô hàng nào sắp hết hạn trong 7 ngày tới, cần ưu tiên xuất FEFO?',
        category: 'expiry',
        icon: Package,
    },
    {
        text: 'Có điều chuyển nội bộ nào đang chờ xử lý hoặc có ngoại lệ chênh lệch?',
        category: 'transfers',
        icon: ArrowLeftRight,
    },
    {
        text: 'Tổng giá trị tồn kho hiện tại và mặt hàng nào chiếm tỷ trọng vốn lớn nhất?',
        category: 'cost',
        icon: BadgeDollarSign,
    },
    {
        text: 'Tác vụ kho nào đang quá hạn và cần điều phối thêm nhân sự xử lý?',
        category: 'tasks',
        icon: Users,
    },
];

const suggestions = computed(() =>
    isCentralWarehouse.value ? warehouseSuggestions : ownerSuggestions,
);

function welcomeContent(): string {
    if (isCentralWarehouse.value) {
        return `Xin chào **${user.value?.name || 'Trưởng kho'}**! Tôi là **Trợ lý AI Kho Tổng**. 📦\n\nTôi tập trung chuyên sâu vào công tác vận hành kho và tối ưu chuỗi cung ứng:\n• **Tồn khả dụng & Cấp phát**: Đảm bảo đủ tồn khả dụng (đã trừ giữ chỗ) để đáp ứng các đơn cấp phát chi nhánh.\n• **SLA & Chất lượng giao hàng**: Giám sát đơn trễ hạn, tỷ lệ giao đủ - đúng hạn (OTIF) và Fill rate.\n• **Kiểm soát Hạn sử dụng (FEFO)**: Cảnh báo sớm các lô hàng cận date (< 7 ngày) để ưu tiên xuất trước.\n• **Tồn an toàn & Điều chuyển**: Phát hiện mặt hàng dưới mức đặt hàng lại, kiểm soát ngoại lệ điều chuyển.\n• **Giá vốn & Điều phối**: Theo dõi giá trị tồn kho, biến động đơn giá và điều phối nhân sự xử lý công việc.\n\nBạn có thể nhập câu hỏi trực tiếp hoặc chọn nhanh các kịch bản vận hành ở danh mục bên trái.`;
    }

    return `Xin chào **${user.value?.name || 'Chủ doanh nghiệp'}**! Tôi là **Trợ lý AI Chiến lược Doanh nghiệp**. 📊\n\nTôi hỗ trợ bạn phân tích toàn diện bức tranh kinh doanh và đưa ra các quyết định điều hành cấp cao:\n• **Doanh thu & Tăng trưởng**: Theo dõi doanh thu thời gian thực, so sánh các chi nhánh và dự báo tương lai.\n• **Tối ưu Thực đơn (Menu Engineering)**: Nhận diện top món bán chạy, món sinh lời cao và món cần cải thiện.\n• **Kiểm soát Gian lận & Rủi ro**: Giám sát lệch két, hủy hóa đơn bất thường, rủi ro thất thoát tài chính.\n• **Mục tiêu & Dòng tiền**: Đánh giá tiến độ hoàn thành OKR/KPI và sức khỏe dòng tiền kinh doanh.\n\nBạn có thể nhập câu hỏi trực tiếp hoặc chọn nhanh các kịch bản phân tích ở danh mục bên trái.`;
}

onMounted(async () => {
    sessionId.value = getOrCreateSessionId();

    const restored = await loadHistory(sessionId.value);

    if (restored) {
        scrollToBottom();

        return;
    }

    // Welcome message (chỉ hiển thị khi chưa có lịch sử hội thoại)
    messages.value = [
        {
            id: 'welcome',
            role: 'bot',
            content: welcomeContent(),
            timestamp: new Date().toISOString(),
        },
    ];
});

async function loadHistory(session: string): Promise<boolean> {
    try {
        const res = await fetch(
            advisorHistory.url({
                query: {
                    session_id: session,
                    mode: props.advisorMode ?? 'strategic',
                },
            }),
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            },
        );

        if (!res.ok) {
            return false;
        }

        const data = await res.json();
        const history = (data.messages ?? []) as Array<{
            role: 'user' | 'bot';
            content: string;
            timestamp: string;
        }>;

        if (history.length === 0) {
            return false;
        }

        messages.value = history.map((m) => ({
            id: crypto.randomUUID(),
            role: m.role,
            content: m.content,
            timestamp: m.timestamp,
        }));

        return true;
    } catch {
        return false;
    }
}

function getOrCreateSessionId(): string {
    const userId = user.value?.id ? `_${user.value.id}` : '';
    const key = `aventura_advisor_session${userId}_${props.advisorMode ?? 'strategic'}`;
    let id = localStorage.getItem(key);

    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem(key, id);
    }

    return id;
}

function clearSession() {
    try {
        const userId = user.value?.id ? `_${user.value.id}` : '';
        localStorage.removeItem(
            `aventura_advisor_session${userId}_${props.advisorMode ?? 'strategic'}`,
        );
    } catch {
        // Ignore
    }
}

function resetChat() {
    clearSession();
    const userId = user.value?.id ? `_${user.value.id}` : '';
    sessionId.value = crypto.randomUUID();
    localStorage.setItem(
        `aventura_advisor_session${userId}_${props.advisorMode ?? 'strategic'}`,
        sessionId.value,
    );

    messages.value = [
        {
            id: 'welcome',
            role: 'bot',
            content: welcomeContent(),
            timestamp: new Date().toISOString(),
        },
    ];
}

function selectSuggestion(text: string) {
    inputText.value = text;
    sendMessage();
}

async function sendMessage() {
    const text = inputText.value.trim();

    if (!text || isLoading.value) {
        return;
    }

    inputText.value = '';

    // User message
    messages.value.push({
        id: crypto.randomUUID(),
        role: 'user',
        content: text,
        timestamp: new Date().toISOString(),
    });
    scrollToBottom();

    isLoading.value = true;

    try {
        const res = await fetch(advisorMessage.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                session_id: sessionId.value,
                message: text,
                mode: props.advisorMode ?? 'strategic',
            }),
        });

        const data = await res.json().catch(() => ({}));

        if (!res.ok) {
            const message =
                res.status === 419
                    ? 'Phiên đăng nhập đã hết hạn. Vui lòng tải lại trang rồi thử lại.'
                    : res.status === 403
                      ? 'Tài khoản hiện tại không có quyền sử dụng Trợ lý AI Chiến lược.'
                      : data.message ||
                        `Máy chủ Laravel trả lỗi HTTP ${res.status}.`;

            messages.value.push({
                id: crypto.randomUUID(),
                role: 'bot',
                content: `⚠️ ${message}`,
                timestamp: new Date().toISOString(),
            });

            return;
        }

        messages.value.push({
            id: crypto.randomUUID(),
            role: 'bot',
            content:
                data.service_available === false
                    ? `⚠️ ${data.answer ?? 'Chatbot Service hiện không khả dụng.'}`
                    : (data.answer ??
                      'Xin lỗi, tôi không thể xử lý câu trả lời lúc này.'),
            timestamp: new Date().toISOString(),
        });
    } catch {
        messages.value.push({
            id: crypto.randomUUID(),
            role: 'bot',
            content:
                '⚠️ Không thể kết nối đến máy chủ Laravel. Vui lòng kiểm tra mạng hoặc tải lại trang.',
            timestamp: new Date().toISOString(),
        });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop =
                messagesContainer.value.scrollHeight;
        }
    });
}

// Simple Markdown renderer
function renderMarkdown(text: string): string {
    return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(
            /`([^`]+)`/g,
            '<code class="bg-muted px-1 py-0.5 rounded text-xs">$1</code>',
        )
        .replace(/\n/g, '<br>');
}
</script>

<template>
    <Head :title="advisorTitle" />

    <div
        class="mx-auto flex h-[calc(100vh-10rem)] min-h-[500px] w-full max-w-7xl flex-col gap-6 p-4 lg:flex-row lg:p-6"
    >
        <!-- Left Panel: Strategic Suggestions -->
        <div class="flex w-full shrink-0 flex-col lg:h-full lg:min-h-0 lg:w-80">
            <Card
                class="flex h-full min-h-0 flex-col overflow-hidden border border-indigo-500/10 bg-gradient-to-br from-indigo-500/10 via-purple-500/5 to-transparent"
            >
                <CardHeader class="shrink-0 pb-3">
                    <div class="flex items-center gap-2">
                        <Sparkles
                            class="size-5 animate-pulse text-indigo-500"
                        />
                        <CardTitle class="text-base font-bold">
                            {{
                                isCentralWarehouse
                                    ? 'Kịch bản điều hành Kho Tổng'
                                    : 'Gợi ý phân tích AI'
                            }}
                        </CardTitle>
                    </div>
                    <CardDescription class="text-xs">
                        {{
                            isCentralWarehouse
                                ? 'Các câu hỏi nhanh giúp Trưởng kho ưu tiên đúng việc.'
                                : 'Các kịch bản hỏi nhanh dữ liệu vận hành từ hệ thống.'
                        }}
                    </CardDescription>
                </CardHeader>
                <CardContent
                    class="no-scrollbar flex flex-1 [scrollbar-width:none] flex-col gap-2.5 overflow-y-auto pr-1 pb-6 [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        v-for="s in suggestions"
                        :key="s.text"
                        @click="selectSuggestion(s.text)"
                        class="group flex w-full items-center gap-3 rounded-xl border border-border/60 bg-card p-3 text-left text-xs font-semibold text-foreground transition-all hover:border-indigo-500/30 hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 text-indigo-500 transition-transform group-hover:scale-105"
                        >
                            <component :is="s.icon" class="size-4" />
                        </div>
                        <span class="line-clamp-2 flex-1 leading-relaxed">{{
                            s.text
                        }}</span>
                        <ArrowRight
                            class="size-3.5 text-muted-foreground opacity-0 transition-all group-hover:translate-x-0.5 group-hover:opacity-100"
                        />
                    </button>
                </CardContent>
            </Card>
        </div>

        <!-- Right Panel: Chat Window -->
        <Card
            class="relative flex h-full min-h-0 flex-1 flex-col overflow-hidden border-border bg-gradient-to-b from-card to-background"
        >
            <!-- Header -->
            <CardHeader
                class="z-10 shrink-0 border-b border-border/80 bg-card/65 pb-4 backdrop-blur-md"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl border border-indigo-500/20 bg-indigo-500/15 text-indigo-500 shadow-inner"
                        >
                            <Bot class="size-5 animate-pulse" />
                        </div>
                        <div>
                            <CardTitle
                                class="flex items-center gap-1.5 text-sm font-bold"
                            >
                                {{ advisorTitle }} Aventura
                                <span
                                    class="h-2 w-2 animate-ping rounded-full bg-emerald-500"
                                ></span>
                            </CardTitle>
                            <CardDescription
                                class="text-xxs font-extrabold tracking-wider text-indigo-600 uppercase dark:text-indigo-400"
                            >
                                {{ advisorSubtitle }}
                            </CardDescription>
                        </div>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        @click="resetChat"
                        title="Làm mới cuộc trò chuyện"
                        class="h-8 gap-1.5 text-xs text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    >
                        <RotateCcw class="size-3.5" />
                        Làm mới hội thoại
                    </Button>
                </div>
            </CardHeader>

            <!-- Chat message area -->
            <div
                ref="messagesContainer"
                class="flex-1 space-y-4 overflow-y-auto scroll-smooth px-5 py-6"
            >
                <div
                    v-for="msg in messages"
                    :key="msg.id"
                    :class="[
                        'flex max-w-[85%] gap-3 transition-all',
                        msg.role === 'user' ? 'ml-auto flex-row-reverse' : '',
                    ]"
                >
                    <!-- Avatar -->
                    <div
                        :class="[
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border text-xs font-bold shadow-sm',
                            msg.role === 'bot'
                                ? 'border-indigo-500/20 bg-indigo-500/10 text-indigo-500'
                                : 'border-border bg-muted text-foreground',
                        ]"
                    >
                        <Bot v-if="msg.role === 'bot'" class="size-4" />
                        <User v-else class="size-4" />
                    </div>

                    <!-- Chat bubble -->
                    <div class="flex flex-col gap-1">
                        <div
                            :class="[
                                'rounded-2xl border p-3.5 text-xs leading-relaxed',
                                msg.role === 'user'
                                    ? 'rounded-tr-none border-indigo-700 bg-indigo-600 text-white shadow-md shadow-indigo-600/10'
                                    : 'rounded-tl-none border-border/80 bg-muted/50 text-foreground',
                            ]"
                        >
                            <span v-html="renderMarkdown(msg.content)"></span>
                        </div>
                    </div>
                </div>

                <!-- Typing indicator -->
                <div v-if="isLoading" class="flex max-w-[85%] gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-500"
                    >
                        <Bot class="size-4" />
                    </div>
                    <div
                        class="flex items-center gap-1 rounded-2xl rounded-tl-none border border-border/60 bg-muted/40 px-4 py-3"
                    >
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-indigo-500/60 [animation-delay:0ms]"
                        />
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-indigo-500/60 [animation-delay:150ms]"
                        />
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-indigo-500/60 [animation-delay:300ms]"
                        />
                    </div>
                </div>
            </div>

            <!-- Input area -->
            <div
                class="z-10 shrink-0 border-t border-border bg-card/65 p-4 backdrop-blur-md"
            >
                <div
                    class="flex items-center gap-2 rounded-2xl border border-border bg-muted/40 px-4 py-2.5 transition focus-within:border-indigo-500/40 focus-within:bg-background focus-within:shadow-md"
                >
                    <input
                        v-model="inputText"
                        @keydown="handleKeydown"
                        type="text"
                        :placeholder="advisorPlaceholder"
                        maxlength="500"
                        class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                    />
                    <Button
                        @click="sendMessage"
                        :disabled="!inputText.trim() || isLoading"
                        size="icon"
                        class="h-8 w-8 shrink-0 rounded-xl bg-indigo-600 text-white transition hover:bg-indigo-500"
                    >
                        <Loader2 v-if="isLoading" class="size-4 animate-spin" />
                        <Send v-else class="size-4" />
                    </Button>
                </div>
            </div>
        </Card>
    </div>
</template>

<style scoped>
.text-xxs {
    font-size: 0.65rem;
}

.no-scrollbar {
    -ms-overflow-style: none; /* IE and Edge */
    scrollbar-width: none; /* Firefox */
}

.no-scrollbar::-webkit-scrollbar {
    display: none; /* Chrome, Safari and Opera */
}
</style>
