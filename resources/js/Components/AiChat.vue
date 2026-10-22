<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { RotateCcw, Send } from 'lucide-vue-next';
import { isValidMessage, MAX_LENGTH, trimHistory } from '@/platform/chat';

const props = defineProps({
    online: { type: Boolean, default: true },
    maxMessages: { type: Number, default: 12 },
});

const STORAGE_KEY = 'kod:ai-chat:v1';
const STORED_LIMIT = 20;
const REQUEST_TIMEOUT = 60000;

const quickPrompts = [
    'Куда сходить с ребёнком?',
    'Что бесплатно?',
    'Собери маршрут на вечер',
    'Как сохранить офлайн?',
];

const page = usePage();
const messages = ref([]);
const input = ref('');
const sending = ref(false);
const error = ref('');
const lastPayload = ref(null);
const logEl = ref(null);
const inputEl = ref(null);

let controller = null;
let cancelled = false;

const canSend = computed(() => props.online && !sending.value && input.value.trim().length > 0);

function scrollToBottom() {
    nextTick(() => {
        if (logEl.value) logEl.value.scrollTop = logEl.value.scrollHeight;
    });
}

function restore() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '[]');
        messages.value = Array.isArray(saved) ? saved.filter(isValidMessage).slice(-STORED_LIMIT) : [];
    } catch {
        messages.value = [];
    }
}

function persist() {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages.value.slice(-STORED_LIMIT)));
    } catch {
        // Приватный режим или переполненная квота: чат работает и без сохранения истории.
    }
}

function resize() {
    nextTick(() => {
        const el = inputEl.value;
        if (!el) return;
        const max = 120;
        el.style.height = 'auto';
        const height = Math.min(el.scrollHeight, max);
        el.style.height = `${height}px`;
        el.style.overflowY = el.scrollHeight > max ? 'auto' : 'hidden';
    });
}

watch(input, resize);

function onEnter(event) {
    if (event.isComposing) return;
    event.preventDefault();
    send();
}

async function send() {
    if (!canSend.value) return;

    const text = input.value.trim();
    if (text.length > MAX_LENGTH) {
        error.value = 'Сообщение длиннее 2 000 символов.';
        return;
    }

    messages.value.push({ role: 'user', content: text });
    input.value = '';
    error.value = '';
    lastPayload.value = trimHistory(messages.value, props.maxMessages);
    persist();
    resize();
    await request(lastPayload.value);
}

async function sendQuick(question) {
    if (!props.online || sending.value) return;
    input.value = question;
    await send();
}

async function retry() {
    if (!props.online || sending.value || !lastPayload.value) return;
    await request(lastPayload.value);
}

async function request(payload) {
    sending.value = true;
    error.value = '';
    scrollToBottom();

    const current = new AbortController();
    controller = current;
    const timer = setTimeout(() => current.abort(), REQUEST_TIMEOUT);

    try {
        const response = await fetch('/platform/ai/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': page.props.csrfToken,
            },
            body: JSON.stringify({ messages: payload }),
            signal: current.signal,
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            const detail = Object.values(result.errors ?? {}).flat()[0];
            if ([502, 503, 504].includes(response.status)) {
                throw new Error('Помощник временно недоступен. Попробуйте позже.');
            }
            if (response.status === 422) {
                throw new Error(detail ?? 'Проверьте сообщение.');
            }
            if (response.status === 429) {
                throw new Error(result.message ?? 'Слишком много сообщений. Попробуйте через минуту.');
            }
            throw new Error(detail ?? result.message ?? 'Не удалось получить ответ.');
        }

        const reply = String(result.reply ?? '').trim();
        if (!reply) throw new Error('Помощник вернул пустой ответ. Попробуйте ещё раз.');

        messages.value.push({ role: 'assistant', content: reply });
        persist();
    } catch (e) {
        if (cancelled) return;
        if (e.name === 'AbortError') {
            error.value = 'Помощник не ответил вовремя. Попробуйте ещё раз.';
        } else if (e instanceof TypeError) {
            error.value = 'Нет связи с сервером. Проверьте подключение и попробуйте снова.';
        } else {
            error.value = e.message || 'Помощник временно недоступен. Попробуйте позже.';
        }
    } finally {
        clearTimeout(timer);
        if (controller === current) controller = null;
        if (cancelled) return;
        sending.value = false;
        scrollToBottom();
    }
}

function reset() {
    if (sending.value) return;
    messages.value = [];
    input.value = '';
    error.value = '';
    lastPayload.value = null;
    try {
        sessionStorage.removeItem(STORAGE_KEY);
    } catch {
        // Хранилище недоступно — очищать нечего.
    }
    resize();
    scrollToBottom();
}

onMounted(() => {
    restore();
    resize();
    scrollToBottom();
});

onBeforeUnmount(() => {
    cancelled = true;
    controller?.abort();
});
</script>

<template>
    <div class="ai-chat">
        <div
            ref="logEl"
            class="ai-chat__log"
            role="log"
            aria-live="polite"
            :aria-busy="sending"
        >
            <div class="ai-chat__intro">
                <strong>Чем помочь?</strong>
                <p>Спросите о местах, маршрутах, событиях или работе платформы.</p>
            </div>

            <div
                v-for="(m, i) in messages"
                :key="i"
                class="ai-chat__msg"
                :class="`ai-chat__msg--${m.role}`"
            >{{ m.content }}</div>

            <div v-if="sending" class="ai-chat__msg ai-chat__msg--assistant ai-chat__typing">
                Печатает<span class="ai-chat__dots" aria-hidden="true"><i></i><i></i><i></i></span>
            </div>
        </div>

        <div v-if="error" class="ai-chat__error" role="alert">
            <span>{{ error }}</span>
            <button
                v-if="lastPayload"
                type="button"
                :disabled="!online || sending"
                @click="retry"
            >
                <RotateCcw :size="13" />Повторить
            </button>
        </div>

        <div v-if="!messages.length" class="ai-chat__chips">
            <button
                v-for="q in quickPrompts"
                :key="q"
                type="button"
                :disabled="!online || sending"
                @click="sendQuick(q)"
            >
                {{ q }}
            </button>
        </div>

        <form class="ai-chat__form" @submit.prevent="send">
            <textarea
                ref="inputEl"
                v-model="input"
                maxlength="2000"
                rows="1"
                aria-label="Сообщение ИИ-помощнику"
                placeholder="Напишите вопрос…"
                :disabled="!online"
                @keydown.enter.exact="onEnter"
            ></textarea>
            <button
                type="submit"
                class="ai-chat__send"
                :disabled="!canSend"
                aria-label="Отправить"
            >
                <Send :size="18" />
            </button>
        </form>

        <p v-if="!online" class="ai-chat__offline">Чат требует подключения к сети</p>

        <div class="ai-chat__foot">
            <small>Enter — отправить, Shift+Enter — новая строка</small>
            <button type="button" class="ai-chat__clear" :disabled="sending" @click="reset">Новый диалог</button>
        </div>

        <p class="ai-chat__disclaimer">
            ИИ может ошибаться. Проверяйте часы работы и условия перед посещением.
        </p>
    </div>
</template>

<style scoped>
.ai-chat {
    margin-top: 18px;
}

.ai-chat__log {
    max-height: 440px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 18px;
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 18px;
}

.ai-chat__intro {
    padding: 4px 2px 10px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.7;
}

.ai-chat__intro strong {
    display: block;
    margin-bottom: 3px;
    color: var(--text);
    font-size: 13px;
}

.ai-chat__intro p {
    margin: 0;
}

.ai-chat__msg {
    max-width: 85%;
    padding: 12px 15px;
    border-radius: 16px;
    font-size: 13px;
    line-height: 1.7;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.ai-chat__msg--user {
    align-self: flex-end;
    background: var(--accent);
    color: var(--on-accent);
    border-bottom-right-radius: 6px;
}

.ai-chat__msg--assistant {
    align-self: flex-start;
    background: var(--soft);
    color: var(--text);
    border-bottom-left-radius: 6px;
}

.ai-chat__typing {
    display: inline-flex;
    align-items: center;
    color: var(--muted);
    font-style: italic;
}

.ai-chat__dots {
    display: inline-flex;
    gap: 3px;
    margin-left: 6px;
}

.ai-chat__dots i {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: currentColor;
    animation: ai-chat-blink 1.2s infinite ease-in-out;
}

.ai-chat__dots i:nth-child(2) {
    animation-delay: .18s;
}

.ai-chat__dots i:nth-child(3) {
    animation-delay: .36s;
}

@keyframes ai-chat-blink {
    0%, 80%, 100% { opacity: .25; }
    40% { opacity: 1; }
}

.ai-chat__error {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin: 10px 0;
    color: var(--rust);
    font-size: 12px;
    line-height: 1.6;
}

.ai-chat__error button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: inherit;
    font-size: 12px;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.ai-chat__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 12px 0;
}

.ai-chat__chips button {
    min-height: 36px;
    padding: 7px 12px;
    border: 1px solid var(--line);
    border-radius: 999px;
    background: var(--paper);
    color: var(--text);
    font-size: 11px;
}

.ai-chat__chips button:hover:not(:disabled) {
    background: var(--soft);
}

.ai-chat__form {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    margin-top: 12px;
}

.ai-chat__form textarea {
    flex: 1;
    min-height: 44px;
    max-height: 120px;
    padding: 11px 13px;
    resize: none;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--paper);
    color: var(--text);
    font-size: 13px;
    line-height: 1.5;
    overflow-y: hidden;
}

.ai-chat__form textarea:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.ai-chat__send {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    flex-shrink: 0;
    border: 1px solid var(--accent);
    border-radius: 12px;
    background: var(--accent);
    color: var(--on-accent);
}

.ai-chat__send:disabled {
    opacity: .45;
}

.ai-chat__offline {
    margin: 8px 0 0;
    color: var(--muted);
    font-size: 11px;
}

.ai-chat__foot {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 8px 14px;
    margin-top: 8px;
    color: var(--muted);
    font-size: 10px;
}

.ai-chat__clear {
    color: var(--muted);
    font-size: 10px;
    text-decoration: underline;
    text-underline-offset: 3px;
}

.ai-chat__disclaimer {
    margin: 12px 0 0;
    color: var(--muted);
    font-size: 10px;
    line-height: 1.6;
}

@media (max-width: 700px) {
    .ai-chat__log {
        max-height: 50vh;
    }

    .ai-chat__msg {
        max-width: 92%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .ai-chat__dots i {
        animation: none;
        opacity: .6;
    }
}
</style>
