<script setup lang="ts">
import { Bot, ChevronDown, Loader2, Send, ThumbsDown, ThumbsUp, X } from 'lucide-vue-next';
import { nextTick, onMounted, ref } from 'vue';

const props = withDefaults(defineProps<{
    source?: string;
}>(), {
    source: 'widget',
});

// ── Types ────────────────────────────────────────────────────────────────────
interface Message {
    id: string;
    role: 'user' | 'bot';
    content: string;
    knowledgeId?: number | null;
    feedback?: 'helpful' | 'unhelpful' | null;
    timestamp: string;
}

interface Suggestion {
    id: number;
    question: string;
    category: string;
}

// ── State ────────────────────────────────────────────────────────────────────
const isOpen = ref(false);
const isLoading = ref(false);
const inputText = ref('');
const messages = ref<Message[]>([]);
const suggestions = ref<Suggestion[]>([]);
const sessionId = ref('');
const messagesContainer = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const hasSentFirstMessage = ref(false);

// ── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
    sessionId.value = getOrCreateSessionId();
    await loadSuggestions();
});

// ── Session ──────────────────────────────────────────────────────────────────
function getOrCreateSessionId(): string {
    const key = 'aventura_chatbot_session';
    let id = localStorage.getItem(key);

    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem(key, id);
    }

    return id;
}

// ── Suggestions ──────────────────────────────────────────────────────────────
async function loadSuggestions() {
    try {
        const res = await fetch('/api/chatbot/suggestions?limit=5');
        const data = await res.json();
        suggestions.value = data.suggestions ?? [];
    } catch {
        suggestions.value = [];
    }
}

// ── Chat actions ─────────────────────────────────────────────────────────────
function toggleOpen() {
    isOpen.value = !isOpen.value;

    if (isOpen.value) {
        nextTick(() => inputRef.value?.focus());
    }
}

function selectSuggestion(question: string) {
    inputText.value = question;
    sendMessage();
}

async function sendMessage() {
    const text = inputText.value.trim();

    if (!text || isLoading.value) {
return;
}

    inputText.value = '';
    hasSentFirstMessage.value = true;

    const userMsg: Message = {
        id: crypto.randomUUID(),
        role: 'user',
        content: text,
        timestamp: new Date().toISOString(),
    };
    messages.value.push(userMsg);
    scrollToBottom();

    isLoading.value = true;

    try {
        const res = await fetch('/api/chatbot/message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ session_id: sessionId.value, message: text, source: props.source }),
        });
        const data = await res.json();
        const botMsg: Message = {
            id: crypto.randomUUID(),
            role: 'bot',
            content: data.answer ?? 'Xin lỗi, tôi không thể trả lời lúc này.',
            knowledgeId: data.knowledge_id ?? null,
            feedback: null,
            timestamp: new Date().toISOString(),
        };
        messages.value.push(botMsg);

        // Cập nhật suggestions từ kết quả trả về (nếu có)
        if (data.suggestions?.length) {
            suggestions.value = data.suggestions.map((q: string, i: number) => ({
                id: i,
                question: q,
                category: data.category ?? '',
            }));
        }
    } catch {
        messages.value.push({
            id: crypto.randomUUID(),
            role: 'bot',
            content: 'Xin lỗi, có lỗi xảy ra. Vui lòng thử lại sau.',
            timestamp: new Date().toISOString(),
        });
    } finally {
        isLoading.value = false;
        await nextTick();
        scrollToBottom();
        inputRef.value?.focus();
    }
}

async function sendFeedback(msg: Message, helpful: boolean) {
    if (!msg.knowledgeId || msg.feedback) {
return;
}

    msg.feedback = helpful ? 'helpful' : 'unhelpful';

    try {
        await fetch('/api/chatbot/feedback', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ knowledge_id: msg.knowledgeId, helpful }),
        });
    } catch {
        // silent fail
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

// ── Markdown-lite renderer (bold + newlines only) ─────────────────────────────
function renderMarkdown(text: string): string {
    return text
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n/g, '<br>');
}
</script>

<template>
    <!-- Floating button -->
    <div class="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3">

        <!-- Chat window -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-4 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <div
                v-if="isOpen"
                class="flex w-[360px] max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-2xl border border-border bg-background shadow-2xl"
                style="height: 520px"
            >
                <!-- Header -->
                <div class="flex items-center justify-between bg-primary px-4 py-3">
                    <div class="flex items-center gap-2">
                        <div class="flex size-8 items-center justify-center rounded-full bg-primary-foreground/20">
                            <Bot class="size-4 text-primary-foreground" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-primary-foreground">Trợ lý Aventura</p>
                            <p class="text-xs text-primary-foreground/70">Luôn sẵn sàng hỗ trợ bạn</p>
                        </div>
                    </div>
                    <button
                        @click="toggleOpen"
                        class="rounded-full p-1 text-primary-foreground/70 transition hover:bg-primary-foreground/20 hover:text-primary-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Messages area -->
                <div
                    ref="messagesContainer"
                    class="flex-1 overflow-y-auto px-3 py-4 scroll-smooth"
                >
                    <!-- Welcome + Suggestions (before first message) -->
                    <div v-if="!hasSentFirstMessage" class="flex flex-col gap-3">
                        <div class="flex gap-2">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10">
                                <Bot class="size-3.5 text-primary" />
                            </div>
                            <div class="max-w-[85%] rounded-2xl rounded-tl-sm bg-muted px-3 py-2 text-sm text-foreground">
                                Xin chào! Tôi là trợ lý Aventura. Hỏi tôi bất cứ điều gì về hệ thống nhé! 👋
                            </div>
                        </div>

                        <!-- Suggested questions -->
                        <div v-if="suggestions.length" class="flex flex-col gap-1.5 pl-9">
                            <p class="text-xs text-muted-foreground">Câu hỏi phổ biến:</p>
                            <button
                                v-for="s in suggestions"
                                :key="s.id"
                                @click="selectSuggestion(s.question)"
                                class="rounded-lg border border-border bg-background px-3 py-1.5 text-left text-xs text-foreground transition hover:border-primary/50 hover:bg-primary/5 hover:text-primary"
                            >
                                {{ s.question }}
                            </button>
                        </div>
                    </div>

                    <!-- Message bubbles -->
                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="mb-3 flex gap-2"
                        :class="msg.role === 'user' ? 'flex-row-reverse' : 'flex-row'"
                    >
                        <!-- Avatar -->
                        <div
                            v-if="msg.role === 'bot'"
                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 self-end"
                        >
                            <Bot class="size-3.5 text-primary" />
                        </div>

                        <!-- Bubble -->
                        <div class="flex max-w-[80%] flex-col gap-1">
                            <div
                                class="rounded-2xl px-3 py-2 text-sm leading-relaxed"
                                :class="msg.role === 'user'
                                    ? 'rounded-tr-sm bg-primary text-primary-foreground'
                                    : 'rounded-tl-sm bg-muted text-foreground'"
                                v-html="msg.role === 'bot' ? renderMarkdown(msg.content) : msg.content"
                            />

                            <!-- Feedback buttons (bot only) -->
                            <div
                                v-if="msg.role === 'bot' && msg.knowledgeId"
                                class="flex items-center gap-1 pl-1"
                            >
                                <span class="text-xs text-muted-foreground">Hữu ích không?</span>
                                <button
                                    @click="sendFeedback(msg, true)"
                                    :disabled="!!msg.feedback"
                                    :class="msg.feedback === 'helpful' ? 'text-green-600' : 'text-muted-foreground hover:text-green-600'"
                                    class="rounded p-0.5 transition disabled:opacity-50"
                                    title="Hữu ích"
                                >
                                    <ThumbsUp class="size-3" />
                                </button>
                                <button
                                    @click="sendFeedback(msg, false)"
                                    :disabled="!!msg.feedback"
                                    :class="msg.feedback === 'unhelpful' ? 'text-red-500' : 'text-muted-foreground hover:text-red-500'"
                                    class="rounded p-0.5 transition disabled:opacity-50"
                                    title="Không hữu ích"
                                >
                                    <ThumbsDown class="size-3" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Typing indicator -->
                    <div v-if="isLoading" class="flex items-center gap-2">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <Bot class="size-3.5 text-primary" />
                        </div>
                        <div class="flex items-center gap-1 rounded-2xl rounded-tl-sm bg-muted px-3 py-2.5">
                            <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground/60 [animation-delay:0ms]" />
                            <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground/60 [animation-delay:150ms]" />
                            <span class="size-1.5 animate-bounce rounded-full bg-muted-foreground/60 [animation-delay:300ms]" />
                        </div>
                    </div>

                    <!-- Suggestions after reply -->
                    <div
                        v-if="hasSentFirstMessage && !isLoading && suggestions.length"
                        class="mt-3 flex flex-col gap-1.5 pl-9"
                    >
                        <p class="text-xs text-muted-foreground">Câu hỏi liên quan:</p>
                        <button
                            v-for="(s, i) in suggestions.slice(0, 3)"
                            :key="i"
                            @click="selectSuggestion(s.question)"
                            class="rounded-lg border border-border bg-background px-3 py-1.5 text-left text-xs text-foreground transition hover:border-primary/50 hover:bg-primary/5 hover:text-primary"
                        >
                            {{ s.question }}
                        </button>
                    </div>
                </div>

                <!-- Input area -->
                <div class="border-t border-border px-3 py-3">
                    <div class="flex items-center gap-2 rounded-xl border border-border bg-muted/40 px-3 py-2 transition focus-within:border-primary/50 focus-within:bg-background">
                        <input
                            ref="inputRef"
                            v-model="inputText"
                            @keydown="handleKeydown"
                            type="text"
                            placeholder="Nhập câu hỏi của bạn..."
                            maxlength="500"
                            class="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                        <button
                            @click="sendMessage"
                            :disabled="!inputText.trim() || isLoading"
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-foreground transition hover:bg-primary/90 disabled:opacity-40"
                        >
                            <Loader2 v-if="isLoading" class="size-3.5 animate-spin" />
                            <Send v-else class="size-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Toggle button -->
        <button
            @click="toggleOpen"
            class="group flex size-14 items-center justify-center rounded-full bg-primary shadow-lg ring-2 ring-primary/20 transition hover:scale-105 hover:shadow-xl active:scale-95"
            :title="isOpen ? 'Đóng trợ lý' : 'Hỏi trợ lý Aventura'"
        >
            <Transition
                enter-active-class="transition duration-200"
                enter-from-class="opacity-0 rotate-90 scale-50"
                enter-to-class="opacity-100 rotate-0 scale-100"
                leave-active-class="transition duration-150"
                leave-from-class="opacity-100 rotate-0 scale-100"
                leave-to-class="opacity-0 rotate-90 scale-50"
                mode="out-in"
            >
                <ChevronDown v-if="isOpen" class="size-6 text-primary-foreground" />
                <Bot v-else class="size-6 text-primary-foreground" />
            </Transition>
            <!-- Pulse ring khi đóng -->
            <span
                v-if="!isOpen"
                class="absolute inline-flex size-14 animate-ping rounded-full bg-primary opacity-20 group-hover:opacity-0"
            />
        </button>
    </div>
</template>
