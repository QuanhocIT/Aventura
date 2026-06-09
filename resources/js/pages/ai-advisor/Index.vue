<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import { 
    Bot, Send, Loader2, Sparkles, AlertCircle, TrendingUp, 
    BarChart3, ShieldAlert, Package, ArrowRight, User
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
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
    { text: 'Doanh thu hôm nay đạt bao nhiêu?', category: 'finance', icon: TrendingUp },
    { text: 'Món nào bán chạy nhất trong ngày?', category: 'sales', icon: BarChart3 },
    { text: 'Có cảnh báo gian lận nào chưa xử lý không?', category: 'fraud', icon: ShieldAlert },
    { text: 'Nguyên liệu nào đang sắp hết trong kho?', category: 'inventory', icon: Package },
    { text: 'Dự báo doanh thu ngày mai thế nào?', category: 'forecast', icon: Sparkles }
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
        timestamp: new Date().toISOString()
    });
});

async function loadHistory(session: string): Promise<boolean> {
    try {
        const res = await fetch(`${route('chatbot.advisor-history')}?session_id=${encodeURIComponent(session)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) return false;

        const data = await res.json();
        const history = (data.messages ?? []) as Array<{ role: 'user' | 'bot'; content: string; timestamp: string }>;
        if (history.length === 0) return false;

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
    if (!text || isLoading.value) return;

    inputText.value = '';
    
    // User message
    messages.value.push({
        id: crypto.randomUUID(),
        role: 'user',
        content: text,
        timestamp: new Date().toISOString()
    });
    scrollToBottom();

    isLoading.value = true;

    try {
        const res = await fetch(route('chatbot.advisor-message'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({
                session_id: sessionId.value,
                message: text
            })
        });

        if (res.ok) {
            const data = await res.json();
            messages.value.push({
                id: crypto.randomUUID(),
                role: 'bot',
                content: data.answer ?? 'Xin lỗi, tôi không thể xử lý câu trả lời lúc này.',
                timestamp: new Date().toISOString()
            });
        } else {
            throw new Error();
        }
    } catch {
        messages.value.push({
            id: crypto.randomUUID(),
            role: 'bot',
            content: '❌ Có lỗi kết nối đến máy chủ AI. Vui lòng kiểm tra lại dịch vụ Python microservice.',
            timestamp: new Date().toISOString()
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
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
}

// Simple Markdown renderer
function renderMarkdown(text: string): string {
    return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/`([^`]+)`/g, '<code class="bg-muted px-1 py-0.5 rounded text-xs">$1</code>')
        .replace(/\n/g, '<br>');
}
</script>

<template>
    <Head title="Trợ lý AI Chiến lược" />

    <div class="flex flex-col lg:flex-row gap-6 p-4 lg:p-6 max-w-7xl mx-auto w-full h-[calc(100vh-10rem)] min-h-[500px]">
        <!-- Left Panel: Strategic Suggestions -->
        <div class="w-full lg:w-80 shrink-0 flex flex-col gap-4">
            <Card class="bg-gradient-to-br from-indigo-500/10 via-purple-500/5 to-transparent border border-indigo-550/10">
                <CardHeader class="pb-3">
                    <div class="flex items-center gap-2">
                        <Sparkles class="size-5 text-indigo-500 animate-pulse" />
                        <CardTitle class="text-base font-bold">Gợi ý phân tích AI</CardTitle>
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
                        class="w-full text-left p-3 rounded-xl border border-border/60 bg-card hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20 hover:border-indigo-500/30 transition-all flex items-center gap-3 group text-xs font-semibold text-foreground"
                    >
                        <div class="h-8 w-8 rounded-lg bg-indigo-500/10 text-indigo-500 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                            <component :is="s.icon" class="size-4" />
                        </div>
                        <span class="flex-1 line-clamp-2 leading-relaxed">{{ s.text }}</span>
                        <ArrowRight class="size-3.5 text-muted-foreground opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
                    </button>
                </CardContent>
            </Card>
        </div>

        <!-- Right Panel: Chat Window -->
        <Card class="flex-1 flex flex-col overflow-hidden bg-gradient-to-b from-card to-background border-border relative">
            <!-- Header -->
            <CardHeader class="border-b border-border/80 pb-4 shrink-0 bg-card/65 backdrop-blur-md z-10">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-2xl bg-indigo-500/15 text-indigo-500 flex items-center justify-center border border-indigo-500/20 shadow-inner">
                        <Bot class="size-5 animate-pulse" />
                    </div>
                    <div>
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            Trợ lý AI Chiến lược Aventura
                            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-ping"></span>
                        </CardTitle>
                        <CardDescription class="text-xxs uppercase tracking-wider font-extrabold text-indigo-600 dark:text-indigo-400">
                            Hệ thống Phân tích Doanh nghiệp Thông minh
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            <!-- Chat message area -->
            <div 
                ref="messagesContainer"
                class="flex-1 overflow-y-auto px-5 py-6 space-y-4 scroll-smooth"
            >
                <div 
                    v-for="msg in messages" 
                    :key="msg.id"
                    :class="['flex gap-3 max-w-[85%] transition-all', msg.role === 'user' ? 'ml-auto flex-row-reverse' : '']"
                >
                    <!-- Avatar -->
                    <div 
                        :class="['h-8 w-8 rounded-xl shrink-0 flex items-center justify-center text-xs font-bold shadow-sm border', 
                                 msg.role === 'bot' ? 'bg-indigo-500/10 text-indigo-500 border-indigo-500/20' : 'bg-muted text-foreground border-border']"
                    >
                        <Bot v-if="msg.role === 'bot'" class="size-4" />
                        <User v-else class="size-4" />
                    </div>

                    <!-- Chat bubble -->
                    <div class="flex flex-col gap-1">
                        <div 
                            :class="['p-3.5 rounded-2xl text-xs leading-relaxed border',
                                     msg.role === 'user' 
                                         ? 'rounded-tr-none bg-indigo-600 text-white border-indigo-700 shadow-md shadow-indigo-600/10' 
                                         : 'rounded-tl-none bg-muted/50 border-border/80 text-foreground']"
                        >
                            <span v-html="renderMarkdown(msg.content)"></span>
                        </div>
                    </div>
                </div>

                <!-- Typing indicator -->
                <div v-if="isLoading" class="flex gap-3 max-w-[85%]">
                    <div class="h-8 w-8 rounded-xl shrink-0 flex items-center justify-center bg-indigo-500/10 text-indigo-500 border border-indigo-500/20">
                        <Bot class="size-4" />
                    </div>
                    <div class="flex items-center gap-1 bg-muted/40 border border-border/60 rounded-2xl rounded-tl-none px-4 py-3">
                        <span class="size-1.5 animate-bounce rounded-full bg-indigo-500/60 [animation-delay:0ms]" />
                        <span class="size-1.5 animate-bounce rounded-full bg-indigo-500/60 [animation-delay:150ms]" />
                        <span class="size-1.5 animate-bounce rounded-full bg-indigo-500/60 [animation-delay:300ms]" />
                    </div>
                </div>
            </div>

            <!-- Input area -->
            <div class="p-4 border-t border-border shrink-0 bg-card/65 backdrop-blur-md z-10">
                <div class="flex items-center gap-2 rounded-2xl border border-border bg-muted/40 px-4 py-2.5 transition focus-within:border-indigo-500/40 focus-within:bg-background focus-within:shadow-md">
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
                        class="h-8 w-8 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl shrink-0 transition"
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
