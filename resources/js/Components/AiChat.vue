<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Bot, Send, Sparkles, Trash2 } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import { useExplorer } from '@/stores/explorer';

const props = defineProps({
    enabled: { type: Boolean, default: true },
    inline: { type: Boolean, default: true },
});

const page = usePage();
const store = useExplorer();

const STORAGE_KEY = 'kod-ai-chat-v1';
const MAX_STORED = 20;
const MAX_MESSAGES_PER_REQUEST = 12;
const MAX_INPUT = 2000;
const MIN_INPUT = 1;

const chips = [
    { label: 'Куда сходить с ребёнком?', prompt: 'Куда сходить с ребёнком?' },
    { label: 'Что бесплатно?', prompt: 'Что бесплатно?' },
    { label: 'Как сохранить офлайн?', prompt: 'Как сохранить каталог офлайн?' },
    { label: 'Собери маршрут на вечер', prompt: 'Собери маршрут на вечер' },
];

const starter = [{ role: 'assistant', content: 'Привет! Я — ИИ-помощник «Кода Новороссии». Подскажу, куда сходить, что посмотреть и как воспользоваться платформой.' }];

const messages = ref(load());
const input = ref('');
const sending = ref(false);
const error = ref('');
const lastPayload = ref(null);
const thread = ref(null);
const field = ref(null);

const online = computed(() => (store?.online !== undefined ? store.online : navigator.onLine));

const canSend = computed(() => {
    if (! props.enabled || sending.value || ! online.value) return false;
    const text = input.value.trim();
    return text.length >= MIN_INPUT && text.length <= MAX_INPUT;
});

function load() {
    if (typeof sessionStorage === 'undefined') return [...starter];
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (! raw) return [...starter];
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length) {
            return parsed.filter(m => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string');
        }
    } catch (e) {
        // приватный режим — игнорируем
    }
    return [...starter];
}

function save() {
    if (typeof sessionStorage === 'undefined') return;
    try {
        const toStore = messages.value.slice(-MAX_STORED);
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(toStore));
    } catch (e) {
        // приватный режим — игнорируем
    }
}

function scroll() {
    nextTick(() => {
        if (thread.value) {
            thread.value.scrollTop = thread.value.scrollHeight;
        }
    });
}

async function sendWith(payload) {
    error.value = '';
    const r = await fetch('/platform/ai/chat', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': page.props.csrfToken,
        },
        body: JSON.stringify({ messages: payload }),
    });

    let result = {};
    try { result = await r.json(); } catch (e) { result = {}; }

    if (! r.ok) {
        const msg = result.message || 'Помощник временно недоступен. Попробуйте позже.';
        throw new Error(msg);
    }

    return result;
}

async function send(text) {
    const value = (text ?? input.value).trim();
    if (value.length < MIN_INPUT || value.length > MAX_INPUT) return;
    if (sending.value) return;
    if (! online.value) return;

    const userMessage = { role: 'user', content: value };
    messages.value.push(userMessage);
    input.value = '';
    sending.value = true;
    error.value = '';
    scroll();

    const history = messages.value.filter(m => m.role === 'user' || m.role === 'assistant').slice(-MAX_MESSAGES_PER_REQUEST);
    lastPayload.value = history;

    try {
        const res = await sendWith(history);
        if (res && res.reply) {
            messages.value.push({ role: 'assistant', content: String(res.reply) });
            save();
            scroll();
        } else {
            throw new Error(res.message || 'Помощник не ответил.');
        }
    } catch (e) {
        error.value = e.message || 'Помощник временно недоступен. Попробуйте позже.';
        // последний пользовательский ответ можно не дублировать, остаётся в истории
    } finally {
        sending.value = false;
        scroll();
    }
}

function retry() {
    if (! lastPayload.value) return;
    sending.value = true;
    error.value = '';
    sendWith(lastPayload.value)
        .then(res => {
            if (res && res.reply) {
                messages.value.push({ role: 'assistant', content: String(res.reply) });
                save();
                scroll();
            } else {
                error.value = res.message || 'Помощник временно недоступен. Попробуйте позже.';
            }
        })
        .catch(e => {
            error.value = e.message || 'Помощник временно недоступен. Попробуйте позже.';
        })
        .finally(() => {
            sending.value = false;
            scroll();
        });
}

function reset() {
    messages.value = [...starter];
    lastPayload.value = null;
    error.value = '';
    input.value = '';
    if (typeof sessionStorage !== 'undefined') {
        try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) {}
    }
    nextTick(() => field.value?.focus());
}

function onKeydown(e) {
    if (e.key === 'Enter' && ! e.shiftKey) {
        e.preventDefault();
        send();
    }
}

onMounted(() => {
    save();
    scroll();
    if (props.enabled) {
        nextTick(() => field.value?.focus());
    }
});

watch(messages, save, { deep: true });
</script>

<template>
    <div v-if="enabled" class="ai-chat" :class="{ 'ai-chat--inline': inline }">
        <div class="ai-chat__header">
            <div class="ai-chat__title">
                <Bot :size="18" />
                <span>ИИ-помощник</span>
            </div>
            <button class="ai-chat__reset" type="button" @click="reset">
                <Trash2 :size="16" />
                <span>Новый диалог</span>
            </button>
        </div>

        <div ref="thread" class="ai-chat__thread" aria-live="polite">
            <div
                v-for="(message, index) in messages"
                :key="index"
                class="ai-chat__bubble"
                :class="message.role === 'user' ? 'ai-chat__bubble--user' : 'ai-chat__bubble--assistant'"
            >
                <div class="ai-chat__bubble-content">{{ message.content }}</div>
            </div>

            <div v-if="sending" class="ai-chat__bubble ai-chat__bubble--assistant">
                <div class="ai-chat__typing">печатает…</div>
            </div>
        </div>

        <div class="ai-chat__chips">
            <button
                v-for="chip in chips"
                :key="chip.label"
                class="ai-chat__chip"
                type="button"
                :disabled="sending || !online"
                @click="send(chip.prompt)"
            >
                <Sparkles :size="14" />
                <span>{{ chip.label }}</span>
            </button>
        </div>

        <p v-if="!online" class="ai-chat__hint">Чат требует подключения к сети</p>
        <p v-if="error" class="ai-chat__error">{{ error }}</p>
        <button v-if="error && lastPayload" class="ai-chat__retry" type="button" @click="retry">Повторить</button>

        <form class="ai-chat__form" @submit.prevent="send()">
            <textarea
                ref="field"
                v-model="input"
                class="ai-chat__input"
                :maxlength="MAX_INPUT"
                :disabled="sending || !online"
                placeholder="Напишите вопрос… (Shift+Enter — новая строка)"
                rows="1"
                aria-label="Сообщение для ИИ-помощника"
                @keydown="onKeydown"
            ></textarea>
            <button class="ai-chat__send" type="submit" :disabled="!canSend" aria-label="Отправить">
                <Send :size="18" />
            </button>
        </form>
        <p class="ai-chat__counter">{{ input.trim().length }} / {{ MAX_INPUT }}</p>
    </div>
</template>
