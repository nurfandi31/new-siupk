<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import { parseMarkdownTree } from '../composables/useMarkdown';
import ArtifactCard from './AssistantComponents/ArtifactCard.vue';
import ArtifactModal from './AssistantComponents/ArtifactModal.vue';
import ActionButton from './AssistantComponents/ActionButton.vue';
import PollCard from './AssistantComponents/PollCard.vue';

const FALLBACK_NAME = 'Ariel';

// Global singleton survives module reloads (Vite HMR + Inertia page swaps).
// Vite dev mode may re-evaluate this module on every navigation, so module-level
// `reactive()` would reset. Stashing in `window` keeps the same instance.
if (!window.__assistantState__) {
    window.__assistantState__ = reactive({
        open: false,
        loading: false,
        sending: false,
        typing: false,
        typingLabel: 'Sedang mengetik',
        error: null,
        input: '',
        messages: [],
        pendingConfirmation: null,
        persona: null,
        conversationId: null,
        msgSeq: 0,
        unreadCount: 0,
    });
} else {
    // Migration: drop legacy session-token fields if a stale shape exists
    // from previous orchestrator-microservice versions of this widget.
    delete window.__assistantState__.sessionToken;
    delete window.__assistantState__.endpoint;
    delete window.__assistantState__.expiresAt;
}
const shared = window.__assistantState__;

const rootEl = ref(null);
const listEl = ref(null);
const inputEl = ref(null);
const fileInputEl = ref(null);
const attachedImages = ref([]);

const open = ref(shared.open);
const loading = ref(shared.loading);
const sending = ref(shared.sending);
const typing = ref(shared.typing);
const typingLabel = ref(shared.typingLabel);
const error = ref(shared.error);
const input = ref(shared.input);
const messages = ref(shared.messages);
const pendingConfirmation = ref(shared.pendingConfirmation);
const persona = ref(shared.persona);
const unreadCount = ref(shared.unreadCount || 0);

// Watch wrapper so all `xxx.value = ...` calls round-trip into shared module state,
// otherwise Inertia layout remounts would lose chat history.
watch(open, (v) => (shared.open = v));
watch(loading, (v) => (shared.loading = v));
watch(sending, (v) => (shared.sending = v));
watch(typing, (v) => (shared.typing = v));
watch(typingLabel, (v) => (shared.typingLabel = v));
watch(error, (v) => (shared.error = v));
watch(input, (v) => (shared.input = v));
watch(messages, (v) => (shared.messages = v), { deep: true });
watch(pendingConfirmation, (v) => (shared.pendingConfirmation = v), { deep: true });
watch(persona, (v) => (shared.persona = v), { deep: true });
watch(unreadCount, (v) => (shared.unreadCount = v));

let conversationId = shared.conversationId || null;
let msgSeq = shared.msgSeq;

watch(() => shared.conversationId, (v) => (conversationId = v));

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function markCurrentConversationRead() {
    unreadCount.value = 0;
    if (!conversationId) return;
    try {
        await fetch(`/assistant/conversations/${conversationId}/mark-read`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });
    } catch {
        // ignore
    }
}

function displayName() {
    return persona.value?.name || FALLBACK_NAME;
}

function pushMessage(msg) {
    messages.value.push({ id: ++msgSeq, ...msg });
    shared.msgSeq = msgSeq;
    scrollBottom();
    return messages.value[messages.value.length - 1];
}

function onDocumentPointerDown(event) {
    if (!open.value || !rootEl.value) return;
    const target = event.target;
    if (target instanceof Node && rootEl.value.contains(target)) return;
    open.value = false;
}

document.addEventListener('pointerdown', onDocumentPointerDown, true);
onMounted(() => {
    ensureSession();
    window.addEventListener('assistant:toggle', toggle);
});

function pickGreeting() {
    const name = displayName();
    const hour = new Date().getHours();
    const salam = hour < 11 ? 'Selamat pagi' : hour < 15 ? 'Selamat siang' : hour < 18 ? 'Selamat sore' : 'Selamat malam';
    const pool = [
        `${salam}, apa yang bisa ${name} bantu hari ini?`,
        `Butuh bantuan? ${name} siap membantu.`,
        `Perlu bantuan mencatat transaksi? Mungkin ${name} bisa bantu.`,
        `Halo! ${name} di sini. Ada data yang ingin dicari?`,
        `${salam}. ${name} siap bantu cek angsuran, jurnal, atau data anggota.`,
    ];
    return pool[Math.floor(Math.random() * pool.length)];
}

// In-process mode: backend resolves tenant + user from the authenticated
// session via siupkSessionResolver. No separate session token dance.
let personaPromise = null;

function ensureSession() {
    if (persona.value?.name) return Promise.resolve();
    if (!personaPromise) {
        personaPromise = fetch('/assistant/persona', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then((res) => (res.ok ? res.json() : null))
            .then((data) => {
                if (data?.persona) {
                    persona.value = {
                        id: data.persona.id || null,
                        slug: data.persona.slug || null,
                        name: data.persona.name || FALLBACK_NAME,
                    };
                }
            })
            .catch(() => {})
            .finally(() => {
                personaPromise = null;
            });
    }
    return personaPromise;
}

async function scrollBottom() {
    await nextTick();
    if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight;
}

function resizeInput() {
    const el = inputEl.value;
    if (!el) return;
    el.style.height = 'auto';
    const max = 7.5 * 16; // ~7.5rem ≈ 5 lines
    el.style.height = `${Math.min(el.scrollHeight, max)}px`;
}

async function afterInputChange() {
    await nextTick();
    resizeInput();
}

function parseSseChunk(buffer, onEvent) {
    const parts = buffer.split('\n\n');
    const rest = parts.pop() ?? '';
    for (const block of parts) {
        let event = 'message';
        const dataLines = [];
        for (const line of block.split('\n')) {
            if (line.startsWith('event:')) event = line.slice(6).trim();
            else if (line.startsWith('data:')) dataLines.push(line.slice(5).trim());
        }
        if (!dataLines.length) continue;
        let data = dataLines.join('\n');
        try {
            data = JSON.parse(data);
        } catch {
            // keep raw string
        }
        onEvent(event, data);
    }
    return rest;
}

async function readSse(response, onEvent) {
    if (!response.ok) {
        const text = await response.text();
        throw new Error(text || `HTTP ${response.status}`);
    }
    const reader = response.body?.getReader();
    if (!reader) throw new Error('Stream tidak tersedia.');
    const decoder = new TextDecoder();
    let buffer = '';
    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        buffer = parseSseChunk(buffer, onEvent);
    }
    if (buffer.trim()) parseSseChunk(buffer + '\n\n', onEvent);
}

function handleEvent(event, data, assistantMsg) {
    if (event === 'conversation' && data?.id) {
        conversationId = data.id;
        shared.conversationId = conversationId;
        return;
    }
    if (event === 'text') {
        const delta = typeof data === 'string' ? data : (data?.delta ?? '');
        if (delta) {
            // Push bubble only on the first non-empty delta so it never
            // appears blank, then immediately hide the typing chip in the
            // same reactive batch — Vue commits one render, no flicker.
            if (!assistantMsg._pushed) {
                assistantMsg._pushed = true;
                assistantMsg.content = delta;
                typing.value = false;
                assistantMsg._ref = pushMessage({
                    role: 'assistant',
                    content: assistantMsg.content,
                });
                if (!open.value) unreadCount.value++;
            } else {
                assistantMsg.content += delta;
                if (assistantMsg._ref) assistantMsg._ref.content = assistantMsg.content;
            }
            scrollBottom();
        }
        return;
    }
    if (event === 'tool_progress') {
        typing.value = true;
        if (data?.message) {
            typingLabel.value = data.message;
        } else if (data?.progress) {
            typingLabel.value = `Memproses (${data.progress}%)?`;
        }
        scrollBottom();
        return;
    }
    // Tools stay internal — only status on typing chip (not chat bubbles).
    if (event === 'tool_use') {
        typing.value = true;
        typingLabel.value = 'Mencari data…';
        scrollBottom();
        return;
    }
    if (event === 'tool_result') {
        typing.value = true;
        typingLabel.value = data?.ok === false ? 'Data tidak lengkap, menyusun jawaban…' : 'Menyusun jawaban…';
        scrollBottom();
        return;
    }
    if (event === 'confirmation_required') {
        typing.value = false;
        pendingConfirmation.value = {
            execution_id: data?.execution_id,
            summary: data?.summary || 'Konfirmasi aksi',
            plan: data?.plan || null,
            warnings: data?.warnings || [],
            options: data?.options || [],
            proposed_params: data?.proposed_params || {},
        };
        pushMessage({
            role: 'system',
            content: data?.summary || 'Aksi membutuhkan konfirmasi.',
        });
        return;
    }
    if (event === 'error') {
        typing.value = false;
        const msg = data?.message || 'Terjadi kesalahan asisten.';
        error.value = msg;
        pushMessage({ role: 'error', content: msg });
        return;
    }
    if (event === 'result') {
        if (data?.conversation_id) {
            conversationId = data.conversation_id;
            shared.conversationId = conversationId;
        }
        // Keep typing until text arrived; clear if run ended without text
        if (data?.status && data.status !== 'needs_confirmation' && !assistantMsg.content) {
            typing.value = false;
        }
    }
}

function triggerAttach() {
    fileInputEl.value?.click();
}

function processFiles(files) {
    const validFiles = Array.from(files).filter(
        (f) => f.type.startsWith('image/') || f.type === 'application/pdf'
    );
    for (const file of validFiles) {
        if (file.size > 15 * 1024 * 1024) {
            error.value = 'Ukuran berkas maksimal 15MB.';
            continue;
        }
        const isPdf = file.type === 'application/pdf';
        const reader = new FileReader();
        reader.onload = (e) => {
            attachedImages.value.push({
                dataUrl: e.target.result,
                name: file.name,
                type: file.type,
                size: file.size,
                isPdf,
            });
            nextTick(scrollBottom);
        };
        reader.readAsDataURL(file);
    }
}

function onFilesSelected(event) {
    const files = event.target.files;
    if (files && files.length) {
        processFiles(files);
    }
    event.target.value = '';
}

function onPaste(event) {
    const items = Array.from(event.clipboardData?.items || []);
    const imageFiles = items
        .filter((it) => it.type.startsWith('image/'))
        .map((it) => it.getAsFile())
        .filter(Boolean);
    if (imageFiles.length) {
        processFiles(imageFiles);
    }
}

function removeAttachedImage(index) {
    attachedImages.value.splice(index, 1);
}

async function sendMessage() {
    const content = input.value.trim();
    if (!content && !attachedImages.value.length) return;
    const attachments = attachedImages.value.map((att) => ({
        type: att.isPdf ? 'document' : 'image',
        url: att.dataUrl,
        name: att.name,
        mime: att.type,
    }));
    input.value = '';
    attachedImages.value = [];
    nextTick(resizeInput);
    await sendContent(content, attachments);
}

// --- Interactive component blocks (artifact / button / poll) ---

// Track submitted components per (msgId, blockId) so we can disable + show checkmark.
//   key: `${msgId}__${blockId}` → value: the user's selected text (or '__skip__' / '__other__')
const submittedComponents = reactive(new Map());

function blockKey(msg, block) {
    return `${msg?.id ?? '_'}__${block.id}`;
}

function onComponentSubmit(msg, block, payload) {
    const key = blockKey(msg, block);
    if (submittedComponents.has(key)) return;
    submittedComponents.set(key, payload);

    let text;
    if (payload === '__skip__') text = '(lewati)';
    else if (payload === '__other__') text = block.value || '';
    else text = String(payload);

    // Show user message bubble first; then trigger the SSE flow.
// sendContent handles pushMessage
    nextTick(scrollBottom);
    sendContent(text);
}

// Artifact modal state (only one artifact open at a time).
const previewImageUrl = ref(null);

function previewImage(url) {
    previewImageUrl.value = url;
}

function closeImagePreview() {
    previewImageUrl.value = null;
}

function formatFileSize(bytes) {
    if (!bytes || bytes <= 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

const activeArtifact = ref(null);
function openArtifact(block) {
    activeArtifact.value = block;
}
function closeArtifact() {
    activeArtifact.value = null;
}

// Markdown tree per message. Parsed each call — cheap for chat-sized text,
// and avoids stale-cache issues when content streams incrementally under the
// same proxy identity.
function blocksFor(msg) {
    if (!msg || !msg.content) return [];
    return parseMarkdownTree(msg.content);
}

async function sendContent(content, attachments = []) {
    if ((!content && !attachments.length) || sending.value) return;
    error.value = null;
    pendingConfirmation.value = null;
    pushMessage({
        role: 'user',
        content: content || '(Lampiran Gambar)',
        attachments: attachments.length ? [...attachments] : undefined,
    });
    const assistantMsg = { role: 'assistant', content: '', _pushed: false };
    sending.value = true;
    typing.value = true;
    typingLabel.value = 'Sedang mengetik';
    scrollBottom();
    try {
        await ensureSession();
        const payload = {
            conversation_id: conversationId,
            message: content || 'Berikut lampiran gambar untuk dianalisis.',
        };
        if (attachments && attachments.length) {
            payload.attachments = attachments;
        }
        const res = await fetch('/assistant/chat', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(payload),
        });
        await readSse(res, (event, data) => handleEvent(event, data, assistantMsg));
        if (!assistantMsg._pushed && !assistantMsg.content) {
            pushMessage({
                role: 'assistant',
                content: 'Maaf, saya belum bisa merangkai jawaban. Coba ulangi pertanyaan atau sebutkan lebih spesifik.',
            });
        }
    } catch (e) {
        error.value = e?.message || 'Gagal mengirim pesan.';
        pushMessage({ role: 'error', content: error.value });
    } finally {
        typing.value = false;
        typingLabel.value = 'Sedang mengetik';
        sending.value = false;
        scrollBottom();
    }
}

async function decideConfirmation(decision) {
    const conf = pendingConfirmation.value;
    if (!conf?.execution_id || sending.value) return;
    sending.value = true;
    typing.value = true;
    typingLabel.value = decision === 'approve' ? 'Menjalankan aksi…' : 'Membatalkan…';
    error.value = null;
    const assistantMsg = { role: 'assistant', content: '', _pushed: false };
    scrollBottom();
    try {
        await ensureSession();
        const res = await fetch(`/assistant/confirmations/${conf.execution_id}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ decision }),
        });
        pendingConfirmation.value = null;
        await readSse(res, (event, data) => handleEvent(event, data, assistantMsg));
        if (!assistantMsg._pushed) {
            pushMessage({
                role: 'assistant',
                content: decision === 'approve' ? 'Aksi dijalankan.' : 'Aksi dibatalkan.',
            });
        }
    } catch (e) {
        error.value = e?.message || 'Gagal konfirmasi.';
        pushMessage({ role: 'error', content: error.value });
    } finally {
        typing.value = false;
        typingLabel.value = 'Sedang mengetik';
        sending.value = false;
        scrollBottom();
    }
}

function toggle() {
    open.value = !open.value;
    if (open.value) {
        if (!messages.value.length) {
            pushMessage({ role: 'assistant', content: pickGreeting() });
        }
        markCurrentConversationRead();
        ensureSession();
        nextTick(scrollBottom);
    }
}

function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
        nextTick(resizeInput);
    }
}

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', onDocumentPointerDown, true);
    window.removeEventListener('assistant:toggle', toggle);
});
</script>

<template>
    <div ref="rootEl" class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col items-end gap-3 sm:bottom-6 sm:right-6">
        <Transition name="assistant-panel">
            <div
                v-if="open"
                class="assistant-panel pointer-events-auto flex h-[min(36rem,75vh)] w-[min(24rem,calc(100vw-2rem))] origin-bottom-right flex-col overflow-hidden rounded-2xl floating-shadow bg-surface-container-lowest ring-1 ring-outline-variant/40"
                role="dialog"
                :aria-label="displayName()"
            >
                <div class="flex shrink-0 items-center justify-between gap-2 inset-divider bg-primary px-4 py-3 text-on-primary">
                    <div class="flex min-w-0 items-center gap-2">
                        <AppIcon name="smart_toy" class="shrink-0 text-xl" />
                        <div class="min-w-0">
                            <span class="block truncate text-sm font-bold">{{ displayName() }}</span>
                            <span v-if="persona?.slug" class="block truncate text-[10px] font-medium uppercase tracking-wide text-on-primary/70">{{ persona.slug }}</span>
                        </div>
                    </div>
                    <button type="button" class="rounded-lg p-1 hover:bg-on-primary/10" aria-label="Tutup asisten" @click="open = false">
                        <AppIcon name="close" class="text-xl" />
                    </button>
                </div>

                <p v-if="loading && !messages.length" class="shrink-0 p-4 text-sm text-on-surface-variant">Menghubungkan…</p>
                <p v-else-if="error && !messages.length" class="shrink-0 p-4 text-sm text-error">{{ error }}</p>

                <div ref="listEl" class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto bg-surface p-4">
                    <TransitionGroup name="assistant-msg" tag="div" class="flex flex-col gap-3">
                        <div
                            v-for="msg in messages"
                            :key="msg.id"
                            class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed"
                            :class="{
                                'self-end rounded-br-sm bg-primary text-on-primary whitespace-pre-wrap shadow-sm shadow-primary/30': msg.role === 'user',
                                'assistant-md self-start rounded-bl-sm chip-shadow bg-surface-container-lowest text-on-surface': msg.role === 'assistant' || msg.role === 'system',
                                'self-start rounded-bl-sm bg-error-container text-on-error-container whitespace-pre-wrap': msg.role === 'error',
                                'self-start rounded-bl-sm border border-dashed border-outline-variant bg-surface-container-low text-xs text-on-surface-variant': msg.role === 'tool',
                            }"
                        >
                            <template v-if="msg.role === 'tool'">
                                <span class="font-semibold">{{ msg.kind === 'use' ? 'Tool' : 'Hasil' }}: {{ msg.name }}</span>
                                <span v-if="msg.ok === false" class="text-error"> (gagal)</span>
                            </template>
                            <template v-else-if="msg.role === 'user' || msg.role === 'error'">
                                <div v-if="msg.attachments && msg.attachments.length" class="mb-2 flex flex-col gap-2">
                                    <template v-for="(att, i) in msg.attachments" :key="i">
                                        <!-- Image Bubble Preview -->
                                        <div
                                            v-if="att.type === 'image' || att.mime?.startsWith('image/')"
                                            class="group relative max-w-full overflow-hidden rounded-xl border border-white/20 shadow-sm"
                                        >
                                            <img
                                                :src="att.url"
                                                :alt="att.name || 'Gambar terlampir'"
                                                class="max-h-48 w-full object-cover transition duration-150 group-hover:brightness-95 cursor-pointer"
                                                @click="previewImage(att.url)"
                                            />
                                            <div
                                                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 transition-opacity group-hover:opacity-100"
                                            >
                                                <span class="rounded-full bg-black/60 p-1.5 text-white shadow">
                                                    <AppIcon name="visibility" class="text-base" />
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Document / PDF Bubble Card -->
                                        <div
                                            v-else
                                            class="flex items-center gap-2.5 rounded-xl border border-white/20 bg-black/20 p-2.5 text-on-primary shadow-sm"
                                        >
                                            <div class="grid size-10 shrink-0 place-items-center rounded-lg bg-white/15 text-white">
                                                <AppIcon name="picture_as_pdf" class="text-2xl" />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <span class="block truncate text-xs font-bold leading-tight">{{ att.name || 'Dokumen' }}</span>
                                                <span class="block text-[10px] text-white/75 mt-0.5">{{ formatFileSize(att.size) || 'PDF Dokumen' }}</span>
                                            </div>
                                            <a
                                                v-if="att.url"
                                                :href="att.url"
                                                :download="att.name || 'dokumen.pdf'"
                                                class="grid size-8 shrink-0 place-items-center rounded-lg bg-white/10 text-white transition hover:bg-white/20"
                                                title="Unduh Berkas"
                                            >
                                                <AppIcon name="download" class="text-base" />
                                            </a>
                                        </div>
                                    </template>
                                </div>
                                <span v-if="msg.content && msg.content !== '(Lampiran Gambar)'">{{ msg.content }}</span>
                            </template>
                            <div v-else class="flex flex-col gap-2">
                                <template v-for="block in blocksFor(msg)" :key="block.id">
                                    <h1 v-if="block.type === 'heading' && block.level === 1" class="text-base font-bold">{{ block.text }}</h1>
                                    <h2 v-else-if="block.type === 'heading' && block.level === 2" class="text-sm font-bold">{{ block.text }}</h2>
                                    <h3 v-else-if="block.type === 'heading' && block.level === 3" class="text-sm font-semibold">{{ block.text }}</h3>
                                    <!-- eslint-disable-next-line vue/no-v-html -->
                                    <div
                                        v-else-if="block.type === 'paragraph' || block.type === 'code'"
                                        class="assistant-md-body"
                                        v-html="block.html"
                                    />
                                    <ArtifactCard
                                        v-else-if="block.type === 'artifact'"
                                        :block="block"
                                        @open="openArtifact"
                                    />
                                    <ActionButton
                                        v-else-if="block.type === 'button'"
                                        :block="block"
                                        @submit="(payload) => onComponentSubmit(msg, block, payload)"
                                    />
                                    <PollCard
                                        v-else-if="block.type === 'poll'"
                                        :block="block"
                                        :submitted="submittedComponents.get(`${msg.id}__${block.id}`) ?? null"
                                        @submit="(payload) => onComponentSubmit(msg, block, payload)"
                                    />
                                </template>
                            </div>
                        </div>
                    </TransitionGroup>

                    <div
                        v-if="typing"
                        class="assistant-typing self-start flex max-w-[85%] items-center gap-2 rounded-2xl rounded-bl-sm chip-shadow bg-surface-container-lowest px-3.5 py-2.5"
                        :aria-label="typingLabel"
                    >
                        <span class="flex items-center gap-1">
                            <span class="assistant-typing-dot" />
                            <span class="assistant-typing-dot" />
                            <span class="assistant-typing-dot" />
                        </span>
                        <span class="text-xs text-on-surface-variant">{{ typingLabel }}</span>
                    </div>

                    <div
                        v-if="pendingConfirmation"
                        class="self-stretch rounded-xl chip-shadow bg-surface-container-lowest p-3 text-sm"
                    >
                        <p class="font-semibold text-primary">{{ pendingConfirmation.summary }}</p>
                        <ul v-if="pendingConfirmation.warnings?.length" class="mt-2 list-disc pl-4 text-on-surface-variant">
                            <li v-for="(w, i) in pendingConfirmation.warnings" :key="i">{{ w }}</li>
                        </ul>
                        <div class="mt-3 flex gap-2">
                            <button
                                type="button"
                                class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-on-primary disabled:opacity-50"
                                :disabled="sending"
                                @click="decideConfirmation('approve')"
                            >Setuju</button>
                            <button
                                type="button"
                                class="rounded-lg ring-1 ring-outline-variant px-3 py-1.5 text-xs font-semibold text-on-surface shadow-sm shadow-black/5 disabled:opacity-50"
                                :disabled="sending"
                                @click="decideConfirmation('reject')"
                            >Tolak</button>
                        </div>
                    </div>
                </div>

                <div class="inset-divider-t bg-surface-container-lowest">
                    <!-- Attached Files Preview -->
                    <div v-if="attachedImages.length" class="flex flex-wrap gap-2 inset-divider px-3 pt-2.5 pb-2">
                        <div
                            v-for="(att, idx) in attachedImages"
                            :key="idx"
                            class="group relative flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-outline-variant bg-surface-container"
                        >
                            <img v-if="!att.isPdf" :src="att.dataUrl" class="size-full object-cover" :alt="att.name" />
                            <div v-else class="flex flex-col items-center justify-center p-1 text-center text-on-surface-variant">
                                <AppIcon name="picture_as_pdf" class="text-lg text-primary" />
                                <span class="max-w-12 truncate text-[9px] font-semibold">{{ att.name }}</span>
                            </div>
                            <button
                                type="button"
                                class="absolute inset-0 grid place-items-center bg-black/60 text-white opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100"
                                aria-label="Hapus lampiran"
                                @click="removeAttachedImage(idx)"
                            >
                                <AppIcon name="close" class="text-base" />
                            </button>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-end gap-2 p-3">
                        <input
                            ref="fileInputEl"
                            type="file"
                            accept="image/png,image/jpeg,image/webp,image/gif,application/pdf"
                            multiple
                            class="sr-only"
                            @change="onFilesSelected"
                        />
                        <button
                            type="button"
                            class="mb-0.5 grid size-11 shrink-0 place-items-center rounded-xl chip-shadow ring-1 ring-outline-variant text-on-surface-variant transition hover:bg-surface-container hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50"
                            :disabled="sending || loading"
                            aria-label="Lampirkan Gambar atau Dokumen"
                            title="Lampirkan Gambar atau Dokumen (PDF)"
                            @click="triggerAttach"
                        >
                            <AppIcon name="add_photo_alternate" class="text-xl" />
                        </button>
                        <textarea
                            ref="inputEl"
                            v-model="input"
                            rows="2"
                            class="assistant-composer min-h-[2.75rem] max-h-[7.5rem] flex-1 resize-none overflow-y-auto rounded-xl border border-outline-variant bg-surface px-3 py-2.5 text-sm leading-5 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"
                            :placeholder="`Tulis ke ${displayName()}...`"
                            :disabled="sending || loading"
                            @input="afterInputChange"
                            @keydown="onKeydown"
                            @paste="onPaste"
                        />
                        <button
                            type="button"
                            class="mb-0.5 grid size-11 shrink-0 place-items-center rounded-xl bg-primary text-on-primary disabled:opacity-50"
                            :disabled="sending || loading || (!input.trim() && !attachedImages.length)"
                            aria-label="Kirim"
                            @click="sendMessage"
                        >
                            <AppIcon name="send" class="text-xl" />
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <ArtifactModal :block="activeArtifact" @close="closeArtifact" />

        <!-- Full-Screen Image Lightbox Modal -->
        <Teleport to="body">
            <Transition name="fade">
                <div
                    v-if="previewImageUrl"
                    class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
                    role="dialog"
                    aria-label="Preview Gambar"
                    @click.self="closeImagePreview"
                >
                    <div class="relative max-h-[90vh] max-w-[90vw] overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl">
                        <button
                            type="button"
                            class="absolute top-3 right-3 z-10 grid size-9 place-items-center rounded-full bg-black/60 text-white transition hover:bg-black/80"
                            aria-label="Tutup preview"
                            @click="closeImagePreview"
                        >
                            <AppIcon name="close" class="text-xl" />
                        </button>
                        <img :src="previewImageUrl" alt="Preview Gambar" class="max-h-[85vh] max-w-full object-contain" />
                    </div>
                </div>
            </Transition>
        </Teleport>

        <button
            type="button"
            class="pointer-events-auto relative grid size-14 place-items-center rounded-full bg-primary text-on-primary shadow-lg transition duration-200 hover:scale-105 hover:bg-primary-container focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:scale-95"
            :aria-expanded="open"
            :aria-label="`Buka ${displayName()}`"
            @click="toggle"
        >
            <AppIcon :name="open ? 'close' : 'smart_toy'" class="text-2xl transition-transform duration-200" />
            <span
                v-if="!open && unreadCount > 0"
                class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-error px-1 text-[11px] font-bold text-on-error shadow"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>
    </div>
</template>

<style scoped>
.assistant-composer {
    field-sizing: content;
}
.assistant-composer::-webkit-resizer,
.assistant-composer::-webkit-scrollbar-corner {
    display: none;
}

.assistant-panel-enter-active,
.assistant-panel-leave-active {
    transition:
        opacity 0.2s ease,
        transform 0.22s cubic-bezier(0.22, 1, 0.36, 1);
}
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.assistant-panel-enter-from,
.assistant-panel-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.96);
}

.assistant-msg-enter-active {
    transition:
        opacity 0.2s ease,
        transform 0.2s cubic-bezier(0.22, 1, 0.36, 1);
}
.assistant-msg-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.assistant-typing-dot {
    display: block;
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 9999px;
    background: color-mix(in srgb, var(--color-on-surface) 45%, transparent);
    animation: assistant-typing-bounce 1.2s infinite ease-in-out;
}
.assistant-typing-dot:nth-child(2) {
    animation-delay: 0.15s;
}
.assistant-typing-dot:nth-child(3) {
    animation-delay: 0.3s;
}
@keyframes assistant-typing-bounce {
    0%,
    60%,
    100% {
        transform: translateY(0);
        opacity: 0.45;
    }
    30% {
        transform: translateY(-3px);
        opacity: 1;
    }
}

.assistant-md-body :deep(.assistant-md-p) {
    margin: 0;
}
.assistant-md-body :deep(.assistant-md-p + .assistant-md-p) {
    margin-top: 0.5rem;
}
.assistant-md-body :deep(.assistant-md-ul) {
    margin: 0.35rem 0;
    padding-left: 1.15rem;
    list-style: disc;
}
.assistant-md-body :deep(.assistant-md-ul li) {
    margin: 0.15rem 0;
}
.assistant-md-body :deep(.assistant-md-table) {
    width: 100%;
    margin: 0.5rem 0;
    border-collapse: collapse;
    font-size: 0.78rem;
    line-height: 1.35;
    border: 1px solid color-mix(in srgb, var(--color-on-surface) 10%, transparent);
    border-radius: 0.5rem;
    overflow: hidden;
}
.assistant-md-body :deep(.assistant-md-table thead) {
    background: color-mix(in srgb, var(--color-primary) 14%, transparent);
    color: var(--color-primary);
}
.assistant-md-body :deep(.assistant-md-table th),
.assistant-md-body :deep(.assistant-md-table td) {
    padding: 0.4rem 0.6rem;
    text-align: left;
    border-top: 1px solid color-mix(in srgb, var(--color-on-surface) 8%, transparent);
    vertical-align: top;
}
.assistant-md-body :deep(.assistant-md-table th) {
    font-weight: 700;
    border-top: none;
}
.assistant-md-body :deep(.assistant-md-table tbody tr:nth-child(even)) {
    background: color-mix(in srgb, var(--color-on-surface) 4%, transparent);
}
.assistant-md-body :deep(.assistant-md-table tbody tr:hover) {
    background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}
.assistant-md-body :deep(.assistant-md-h2),
.assistant-md-body :deep(.assistant-md-h3) {
    font-weight: 700;
    margin: 0.35rem 0 0.2rem;
}
.assistant-md-body :deep(.assistant-md-h2) {
    font-size: 0.95rem;
}
.assistant-md-body :deep(.assistant-md-code) {
    font-family: ui-monospace, monospace;
    font-size: 0.8em;
    padding: 0.05rem 0.3rem;
    border-radius: 0.25rem;
    background: color-mix(in srgb, var(--color-on-surface) 8%, transparent);
}
.assistant-md-body :deep(.assistant-md-pre) {
    margin: 0.4rem 0;
    padding: 0.5rem 0.65rem;
    overflow-x: auto;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    line-height: 1.4;
    background: color-mix(in srgb, var(--color-on-surface) 6%, transparent);
}
.assistant-md-body :deep(strong) {
    font-weight: 700;
}

/* Artifact modal content (reuses same md-* classes) */
.assistant-artifact-body :deep(.md-p) {
    margin: 0 0 0.5rem;
}
.assistant-artifact-body :deep(.md-p:last-child) {
    margin-bottom: 0;
}
.assistant-artifact-body :deep(.md-table) {
    width: 100%;
    margin: 0.5rem 0;
    border-collapse: collapse;
    font-size: 0.85rem;
    border: 1px solid color-mix(in srgb, var(--color-on-surface) 12%, transparent);
    border-radius: 0.5rem;
    overflow: hidden;
}
.assistant-artifact-body :deep(.md-table thead) {
    background: color-mix(in srgb, var(--color-primary) 14%, transparent);
    color: var(--color-primary);
}
.assistant-artifact-body :deep(.md-table th),
.assistant-artifact-body :deep(.md-table td) {
    padding: 0.5rem 0.75rem;
    text-align: left;
    border-top: 1px solid color-mix(in srgb, var(--color-on-surface) 8%, transparent);
}
.assistant-artifact-body :deep(.md-table tbody tr:nth-child(even)) {
    background: color-mix(in srgb, var(--color-on-surface) 4%, transparent);
}
.assistant-artifact-body :deep(.md-table tbody tr:hover) {
    background: color-mix(in srgb, var(--color-primary) 8%, transparent);
}
.assistant-artifact-body :deep(.md-pre) {
    margin: 0.5rem 0;
    padding: 0.6rem 0.8rem;
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--color-on-surface) 6%, transparent);
    overflow-x: auto;
    font-size: 0.78rem;
    line-height: 1.45;
}
.assistant-artifact-body :deep(.md-ul) {
    margin: 0.5rem 0;
    padding-left: 1.25rem;
    list-style: disc;
}
</style>