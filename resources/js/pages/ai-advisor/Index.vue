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
    ArrowRight,
    User,
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

defineOptions({ layout: AppLayout });

interface Message {
    id: string;
    role: 'user' | 'bot';
    content: string;
    timestamp: string;
}

const page = usePage();
const user = computed(() => (page.props.auth?.user as any) ?? null);

const messages = ref<Message[]>([]);
const inputText = ref('');
const isLoading = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);
const sessionId = ref('');

// List of suggested strategic questions to WOW the business owner
const strategicSuggestions = [
    {
        text: 'Doanh thu hôm nay đạt bao nhiêu?',
        category: 'finance',
        icon: TrendingUp,
    },
    {
        text: 'Món nào bán chạy nhất trong ngày?',
        category: 'sales',
        icon: BarChart3,
    },
    {
        text: 'Có cảnh báo gian lận nào chưa xử lý không?',
        category: 'fraud',
        icon: ShieldAlert,
    },
    {
        text: 'Nguyên liệu nào đang sắp hết trong kho?',
        category: 'inventory',
        icon: Package,
    },
    {
        text: 'Dự báo doanh thu ngày mai thế nào?',
        category: 'forecast',
        icon: Sparkles,
    },
];

onMounted(async () => {
    sessionId.value = getOrCreateSessionId();

    const restored = await loadHistory(sessionId.value);

    if (restored) {
        scrollToBottom();

        return;
    }

    // Welcome message (chỉ hiển thị khi chưa có lịch sử hội thoại)
    messages.value.push({
        id: 'welcome',
        role: 'bot',
        content: `Xin chào **${user.value?.name || 'Chủ quán'}**! Tôi là **Trợ lý AI Chiến lược** của bạn. 📊\n\nTôi có thể truy xuất số liệu doanh thu thời gian thực, phân tích các nhóm món bán chạy, cảnh báo rủi ro gian lận hoặc dự đoán lượng nguyên liệu kho.\n\nBạn có thể hỏi tôi bất kỳ điều gì, hoặc chọn nhanh các câu hỏi gợi ý bên trái.`,
        timestamp: new Date().toISOString(),
    });
});

async function loadHistory(session: string): Promise<boolean> {
    try {
        const res = await fetch(
            `${route('chatbot.advisor-history')}?session_id=${encodeURIComponent(session)}`,
            {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
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
    const key = 'aventura_advisor_session';
    let id = localStorage.getItem(key);

    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem(key, id);
    }

    return id;
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
        const res = await fetch(route('chatbot.advisor-message'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content ?? '',
            },
            body: JSON.stringify({
                session_id: sessionId.value,
                message: text,
            }),
        });

        if (res.ok) {
            const data = await res.json();
            messages.value.push({
                id: crypto.randomUUID(),
                role: 'bot',
                content:
                    data.answer ??
                    'Xin lỗi, tôi không thể xử lý câu trả lời lúc này.',
                timestamp: new Date().toISOString(),
            });
        } else {
            throw new Error();
        }
    } catch {
        messages.value.push({
            id: crypto.randomUUID(),
            role: 'bot',
            content:
                '❌ Có lỗi kết nối đến máy chủ AI. Vui lòng kiểm tra lại dịch vụ Python microservice.',
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
    <Head title="Trợ lý AI Chiến lược" />

    <div
        class="mx-auto flex h-[calc(100vh-10rem)] min-h-[500px] w-full max-w-7xl flex-col gap-6 p-4 lg:flex-row lg:p-6"
    >
        <!-- Left Panel: Strategic Suggestions -->
        <div class="flex w-full shrink-0 flex-col gap-4 lg:w-80">
            <Card
                class="border-indigo-550/10 border bg-gradient-to-br from-indigo-500/10 via-purple-500/5 to-transparent"
            >
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <Sparkles
                            class="size-5 animate-pulse text-indigo-500"
                        />
                        <CardTitle class="text-base font-bold"
                            >Gợi ý phân tích AI</CardTitle
                        >
                    </div>
                    <CardDescription class="text-xs">
                        Các kịch bản hỏi nhanh dữ liệu vận hành từ hệ thống.
                    </CardDescription>
                </CardHeader>
                <CardContent class="flex flex-col gap-2.5">
                    <button
                        v-for="s in strategicSuggestions"
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
            class="relative flex flex-1 flex-col overflow-hidden border-border bg-gradient-to-b from-card to-background"
        >
            <!-- Header -->
            <CardHeader
                class="z-10 shrink-0 border-b border-border/80 bg-card/65 pb-4 backdrop-blur-md"
            >
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
                            Trợ lý AI Chiến lược Aventura
                            <span
                                class="h-2 w-2 animate-ping rounded-full bg-emerald-500"
                            ></span>
                        </CardTitle>
                        <CardDescription
                            class="text-xxs font-extrabold tracking-wider text-indigo-600 uppercase dark:text-indigo-400"
                        >
                            Hệ thống Phân tích Doanh nghiệp Thông minh
                        </CardDescription>
                    </div>
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
                        placeholder="Hỏi trợ lý chiến lược (ví dụ: Doanh thu hôm nay, cảnh báo gian lận...)"
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
</style>
