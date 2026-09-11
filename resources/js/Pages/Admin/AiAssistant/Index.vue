<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppIconButton from '../../../Components/AppIconButton.vue';
import AppInput from '../../../Components/AppInput.vue';
import { useConfirm } from '../../../composables/useConfirm';
import AppModal from '../../../Components/AppModal.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import ArtifactCard from '../../../Components/AssistantComponents/ArtifactCard.vue';
import ArtifactModal from '../../../Components/AssistantComponents/ArtifactModal.vue';
import ActionButton from '../../../Components/AssistantComponents/ActionButton.vue';
import PollCard from '../../../Components/AssistantComponents/PollCard.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    active_gateway: { type: String, default: 'duitku' },
    xendit: { type: Object, default: () => ({ secret_key: '', public_key: '', has_secret_key: false, mode: 'sandbox', default_method: 'QRIS' }) },
    duitku: { type: Object, default: () => ({ merchant_code: '', has_api_key: false, mode: 'sandbox', default_method: 'VC' }) },
    tripay: { type: Object, default: () => ({ merchant_code: '', has_api_key: false, has_private_key: false, mode: 'sandbox', default_method: 'QRIS2' }) },
    orchestrator: { type: Object, required: true },
    personas: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);

// === Tripay Tab ===
const tripayModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];

const tripayMethodOptions = [
    { value: 'QRIS2', label: 'QRIS (Semua Bank & E-Wallet)' },
    { value: 'BCAVA', label: 'BCA Virtual Account' },
    { value: 'BRIVA', label: 'BRI Virtual Account (BRIVA)' },
    { value: 'BNIVA', label: 'BNI Virtual Account' },
    { value: 'MANDIRIVA', label: 'Mandiri Virtual Account' },
    { value: 'PERMATAVA', label: 'Permata Virtual Account' },
    { value: 'CIMBVA', label: 'CIMB Niaga Virtual Account' },
    { value: 'BSIVA', label: 'BSI Virtual Account' },
    { value: 'DANAMONVA', label: 'Danamon Virtual Account' },
];

const tripayForm = useForm({
    merchant_code: props.tripay?.merchant_code || '',
    api_key: '',
    private_key: '',
    mode: props.tripay?.mode || 'sandbox',
    default_method: props.tripay?.default_method || 'QRIS2',
});

const submitTripay = () => {
    tripayForm.post('/admin/payment-gateways/tripay', {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Tripay tersimpan'),
    });
};

const tripayTestResult = ref(null);
const tripayTesting = ref(false);

const testTripayConnection = async () => {
    tripayTesting.value = true;
    tripayTestResult.value = null;
    try {
        const data = await apiCall('/admin/payment-gateways/tripay/test', { method: 'POST' });
        tripayTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        tripayTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        tripayTesting.value = false;
    }
};



const gatewayForm = useForm({
    gateway: props.active_gateway || 'duitku',
});

const setGateway = (gw) => {
    gatewayForm.gateway = gw;
    gatewayForm.post('/admin/payment-gateways/active', {
        preserveScroll: true,
        onSuccess: () => showToast(`Payment Gateway utama diubah ke ${gw.toUpperCase()}`),
    });
};


// === Xendit Tab ===
const xenditModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];

const xenditMethodOptions = [
    { value: 'QRIS', label: 'QRIS Xendit (Semua Bank & E-Wallet)' },
    { value: 'BCA', label: 'BCA Virtual Account' },
    { value: 'BRI', label: 'BRI Virtual Account' },
    { value: 'BNI', label: 'BNI Virtual Account' },
    { value: 'MANDIRI', label: 'Mandiri Virtual Account' },
    { value: 'PERMATA', label: 'Permata Virtual Account' },
    { value: 'CREDIT_CARD', label: 'Kartu Kredit / Debit (Visa/Mastercard)' },
];

const xenditForm = useForm({
    secret_key: '',
    public_key: props.xendit?.public_key || '',
    callback_token: '',
    mode: props.xendit?.mode || 'sandbox',
    default_method: props.xendit?.default_method || 'QRIS',
});

const submitXendit = () => {
    xenditForm.post('/admin/payment-gateways/xendit', {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Xendit tersimpan'),
    });
};

const xenditTestResult = ref(null);
const xenditTesting = ref(false);

const testXenditConnection = async () => {
    xenditTesting.value = true;
    xenditTestResult.value = null;
    try {
        const data = await apiCall('/admin/payment-gateways/xendit/test', { method: 'POST' });
        xenditTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        xenditTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        xenditTesting.value = false;
    }
};

// === Duitku Tab ===
const duitkuModeOptions = [
    { value: 'sandbox', label: 'Sandbox (Pengujian / Test Mode)' },
    { value: 'production', label: 'Production (Live Transaction)' },
];

const duitkuMethodOptions = [
    { value: 'SP', label: 'ShopeePay / QRIS Duitku' },
    { value: 'BC', label: 'BCA Virtual Account' },
    { value: 'BR', label: 'BRI Virtual Account' },
    { value: 'BN', label: 'BNI Virtual Account' },
    { value: 'M2', label: 'Mandiri Virtual Account' },
    { value: 'VA', label: 'Permata Virtual Account' },
    { value: 'B1', label: 'CIMB Niaga Virtual Account' },
];

const duitkuForm = useForm({
    merchant_code: props.duitku?.merchant_code || '',
    api_key: '',
    mode: props.duitku?.mode || 'sandbox',
    default_method: props.duitku?.default_method || 'VC',
});

const submitDuitku = () => {
    duitkuForm.post('/admin/payment-gateways/duitku', {
        preserveScroll: true,
        onSuccess: () => showToast('Pengaturan Duitku tersimpan'),
    });
};

const duitkuTestResult = ref(null);
const duitkuTesting = ref(false);

const testDuitkuConnection = async () => {
    duitkuTesting.value = true;
    duitkuTestResult.value = null;
    try {
        const data = await apiCall('/admin/payment-gateways/duitku/test', { method: 'POST' });
        duitkuTestResult.value = data;
        showToast(data.message, data.ok ? 'success' : 'error');
    } catch (e) {
        duitkuTestResult.value = { ok: false, message: e.message };
        showToast(e.message, 'error');
    } finally {
        duitkuTesting.value = false;
    }
};

// === Tab state ===
const activeTab = ref('personas');

// === Toast ===
const toast = ref(null);
let toastTimer = null;

const { confirm: askConfirm } = useConfirm();
function showToast(message, tone = 'success') {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { message, tone };
    toastTimer = setTimeout(() => { toast.value = null; }, 3500);
}

// === CSRF ===
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// === Shared fetch helper ===
async function apiCall(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...options.headers,
        },
        credentials: 'same-origin',
        ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.ok === false) {
        throw new Error(data.message || `HTTP ${response.status}`);
    }
    return data;
}

// =====================================================================
// OVERVIEW TAB
// =====================================================================

const testResult = ref(null);
const testLoading = ref(false);
async function testConnection() {
    testLoading.value = true;
    testResult.value = null;
    try {
        testResult.value = await apiCall('/admin/ai-assistant/tools/sync', { method: 'POST' });
    } catch (e) {
        testResult.value = { success: false, status: 'error', message: e.message };
    } finally {
        testLoading.value = false;
    }
}

// =====================================================================
// PERSONAS TAB
// =====================================================================

const personas = ref([...props.personas]);
const tools = ref([...props.tools]);
watch(() => props.personas, (v) => { personas.value = [...v]; });

const personaModal = ref({ open: false, mode: 'create', data: null });
function openCreatePersona() {
    personaModal.value = {
        open: true,
        mode: 'create',
        data: {
            name: '', slug: '', system_prompt: '', is_default: false, is_active: true, tool_ids: [],
        },
    };
}
function openEditPersona(persona) {
    personaModal.value = {
        open: true,
        mode: 'edit',
        data: {
            id: persona.id,
            name: persona.name,
            slug: persona.slug,
            system_prompt: persona.system_prompt,
            is_default: persona.is_default,
            is_active: persona.is_active,
            tool_ids: persona.tools.map((t) => t.id),
        },
    };
}
async function submitPersona() {
    const payload = personaModal.value.data;
    const url = personaModal.value.mode === 'create'
        ? '/admin/ai-assistant/personas/store'
        : `/admin/ai-assistant/personas/${payload.id}`;
    const method = personaModal.value.mode === 'create' ? 'POST' : 'PUT';
    try {
        const data = await apiCall(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        personaModal.value.open = false;
        await refreshPersonas();
        showToast(data.message || 'Persona berhasil disimpan.');
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function deletePersona(persona) {
    const ok = await askConfirm({
        title: 'Hapus Persona',
        message: `Hapus persona "${persona.name}"? Tindakan ini tidak dapat dibatalkan.`,
    });
    if (!ok) return;
    try {
        const data = await apiCall(`/admin/ai-assistant/personas/${persona.id}`, { method: 'DELETE' });
        await refreshPersonas();
        showToast(data.message || 'Persona berhasil dihapus.');
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function togglePersona(persona, field) {
    try {
        const data = await apiCall(`/admin/ai-assistant/personas/${persona.id}/toggle`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ field }),
        });
        await refreshPersonas();
        showToast(data.message || 'Status diperbarui.');
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function refreshPersonas() {
    try {
        const data = await apiCall('/admin/ai-assistant/personas', { method: 'GET' });
        personas.value = data.personas;
    } catch (e) {
        showToast(e.message, 'error');
    }
}

const personaToolOptions = computed(() => tools.value.map((t) => ({
    value: t.id, label: t.name, description: t.description?.slice(0, 60) ?? '',
})));

// =====================================================================
// TOOLS TAB
// =====================================================================

const toolSyncing = ref(false);
async function syncTools() {
    toolSyncing.value = true;
    try {
        const data = await apiCall('/admin/ai-assistant/tools/sync', { method: 'POST' });
        showToast(data.message || 'Sinkronisasi selesai.');
        await refreshTools();
    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        toolSyncing.value = false;
    }
}
async function toggleTool(tool, field) {
    try {
        const payload = { [field]: ! tool[field] };
        await apiCall(`/admin/ai-assistant/tools/${tool.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        await refreshTools();
        showToast('Tool diperbarui.');
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function refreshTools() {
    try {
        const data = await apiCall('/admin/ai-assistant/tools', { method: 'GET' });
        tools.value = data.tools;
    } catch (e) {
        showToast(e.message, 'error');
    }
}

const toolModal = ref({ open: false, tool: null });
function openToolDetail(tool) {
    toolModal.value = { open: true, tool };
}

// =====================================================================
// KNOWLEDGE BASE TAB
// =====================================================================

const docs = ref({ loading: false, items: [], error: '' });
const docModal = ref({ open: false, doc: null });
const uploadForm = reactive({ title: '', persona_id: '', file: null });
const uploadDragOver = ref(false);
const uploadFileName = ref('');
const uploadResult = ref(null);
const uploadLoading = ref(false);

async function fetchDocuments() {
    docs.value.loading = true;
    docs.value.error = '';
    try {
        const params = new URLSearchParams();
        if (uploadForm.persona_id) params.set('persona_id', uploadForm.persona_id);
        const url = '/admin/ai-assistant/documents'
            + (params.toString() ? `?${params.toString()}` : '');
        const data = await apiCall(url, { method: 'GET' });
        docs.value = { loading: false, items: data.items, error: '' };
    } catch (e) {
        docs.value = { loading: false, items: [], error: e.message };
    }
}

const kbPersonaOptions = computed(() => [
    { value: '', label: '— Semua persona —', description: 'Tampilkan semua' },
    ...personas.value.map((p) => ({ value: p.id, label: p.name, description: p.slug })),
]);
const uploadPersonaOptions = computed(() => [
    { value: '', label: '— Tanpa persona (global) —', description: 'Dokumen tersedia global' },
    ...personas.value.map((p) => ({ value: p.id, label: p.name, description: p.slug })),
]);

function onUploadFile(e) {
    const f = e.target.files?.[0] ?? null;
    setUploadFile(f);
}
function onUploadDrop(e) {
    e.preventDefault();
    uploadDragOver.value = false;
    const f = e.dataTransfer?.files?.[0] ?? null;
    setUploadFile(f);
}
function setUploadFile(f) {
    if (!f) return;
    const allowed = ['text/plain', 'text/markdown', 'text/html', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    const ext = (f.name.split('.').pop() || '').toLowerCase();
    if (!allowed.includes(f.type) && !['txt', 'md', 'html', 'pdf', 'docx'].includes(ext)) {
        uploadResult.value = { ok: false, message: 'Tipe file tidak didukung.' };
        return;
    }
    uploadForm.file = f;
    uploadFileName.value = f.name;
    uploadResult.value = null;
    if (!uploadForm.title) uploadForm.title = f.name.replace(/\.[^.]+$/, '');
}

async function submitUpload() {
    if (!uploadForm.file) return;
    uploadResult.value = null;
    uploadLoading.value = true;
    try {
        const fd = new FormData();
        if (uploadForm.title) fd.append('title', uploadForm.title);
        if (uploadForm.persona_id) fd.append('persona_id', uploadForm.persona_id);
        fd.append('file', uploadForm.file);
        const data = await apiCall('/admin/ai-assistant/upload', {
            method: 'POST',
            body: fd,
        });
        uploadResult.value = { ok: true, message: `Berhasil ingest: ${data.document.title}` };
        uploadFileName.value = '';
        uploadForm.file = null;
        uploadForm.title = '';
        await fetchDocuments();
    } catch (e) {
        uploadResult.value = { ok: false, message: e.message };
    } finally {
        uploadLoading.value = false;
    }
}

async function viewDocument(doc) {
    try {
        const data = await apiCall(`/admin/ai-assistant/documents/${doc.id}`, { method: 'GET' });
        docModal.value = { open: true, doc: data.document };
    } catch (e) {
        showToast(e.message, 'error');
    }
}
async function deleteDocument(doc) {
    const ok = await askConfirm({
        title: 'Hapus Dokumen',
        message: `Hapus dokumen "${doc.title}"? Knowledge source juga akan dihapus jika kosong.`,
    });
    if (!ok) return;
    try {
        const data = await apiCall(`/admin/ai-assistant/documents/${doc.id}`, { method: 'DELETE' });
        showToast(data.message || 'Dokumen dihapus.');
        await fetchDocuments();
    } catch (e) {
        showToast(e.message, 'error');
    }
}

// =====================================================================
// TEST CHAT TAB
// =====================================================================

const chatMessages = ref([]);
const chatInput = ref('');
const chatBusy = ref(false);
const chatTyping = ref(false);
const chatTypingLabel = ref('Sedang mengetik');
const chatPersonaId = ref('');
const chatListEl = ref(null);
let chatAbort = null;
let chatSeq = 0;

const chatPersonaOptions = computed(() => [
    { value: '', label: '— Default persona —', description: 'Gunakan persona default sistem' },
    ...personas.value.filter((p) => p.is_active).map((p) => ({ value: p.id, label: p.name, description: p.slug })),
]);

const submittedComponentsTest = reactive(new Map());
const activeArtifactTest = ref(null);
const treeCacheTest = new WeakMap();
function blocksFor(msg) {
    if (!msg || !msg.content) return [];
    let tree = treeCacheTest.get(msg);
    if (!tree) {
        tree = parseMarkdownTree(msg.content);
        treeCacheTest.set(msg, tree);
    }
    return tree;
}
function openArtifact(block) {
    activeArtifactTest.value = block;
}
function onComponentSubmitTest(block, payload) {
    if (submittedComponentsTest.has(block.id)) return;
    submittedComponentsTest.set(block.id, payload);
    const text = payload === '__skip__' ? '(lewati)' : String(payload);
    pushChat({ role: 'user', content: text });
    sendChatTest(text);
}

function pushChat(msg) {
    const id = ++chatSeq;
    chatMessages.value.push({ id, ...msg });
    nextTick(scrollChatBottom);
    return chatMessages.value[chatMessages.value.length - 1];
}

function parseSseBuffer(buffer) {
    const blocks = buffer.split('\n\n');
    const rest = blocks.pop() ?? '';
    const events = [];
    for (const block of blocks) {
        let event = 'message';
        const dataLines = [];
        for (const line of block.split('\n')) {
            if (line.startsWith('event:')) event = line.slice(6).trim();
            else if (line.startsWith('data:')) dataLines.push(line.slice(5).trim());
        }
        if (!dataLines.length) continue;
        let data = dataLines.join('\n');
        try { data = JSON.parse(data); } catch { /* keep raw */ }
        events.push({ event, data });
    }
    return { events, rest };
}

async function readSseStream(response, onEvent) {
    if (!response.ok) throw new Error(`HTTP ${response.status}: ${await response.text()}`);
    const reader = response.body?.getReader();
    if (!reader) throw new Error('Stream tidak tersedia.');
    const decoder = new TextDecoder();
    let buffer = '';
    while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });
        const { events, rest } = parseSseBuffer(buffer);
        buffer = rest;
        for (const e of events) onEvent(e.event, e.data);
    }
    if (buffer.trim()) {
        const { events } = parseSseBuffer(buffer + '\n\n');
        for (const e of events) onEvent(e.event, e.data);
    }
}

function handleChatEvent(event, data, assistantMsg) {
    if (event === 'text') {
        const delta = typeof data === 'string' ? data : (data?.delta ?? '');
        if (delta) {
            if (assistantMsg.id === null) {
                assistantMsg.content = delta;
                chatTyping.value = false;
                const ref = pushChat({ role: 'assistant', content: assistantMsg.content });
                assistantMsg.id = ref.id;
            } else {
                assistantMsg.content += delta;
                const target = chatMessages.value.find((m) => m.id === assistantMsg.id);
                if (target) target.content = assistantMsg.content;
            }
            nextTick(scrollChatBottom);
        }
        return;
    }
    if (event === 'tool_use') {
        chatTyping.value = true;
        chatTypingLabel.value = 'Mencari data…';
        nextTick(scrollChatBottom);
        return;
    }
    if (event === 'tool_result') {
        chatTyping.value = true;
        chatTypingLabel.value = 'Menyusun jawaban…';
        nextTick(scrollChatBottom);
        return;
    }
    if (event === 'confirmation_required') {
        chatTyping.value = false;
        const summary = data?.summary ?? 'Aksi perlu konfirmasi.';
        pushChat({ role: 'assistant', content: `⚠️ï¸ **Konfirmasi diperlukan:** ${summary}` });
        return;
    }
    if (event === 'error') {
        chatTyping.value = false;
        const msg = data?.message || 'Terjadi kesalahan.';
        if (assistantMsg.id === null) {
            assistantMsg.content = msg;
            assistantMsg.id = chatMessages.value.length + 1;
            pushChat({ role: 'error', content: msg });
        }
        return;
    }
}

async function sendChatTest(content) {
    const assistantMsg = { id: null, content: '' };
    chatBusy.value = true;
    chatTyping.value = true;
    chatTypingLabel.value = 'Sedang mengetik';
    chatAbort = new AbortController();
    try {
        const res = await fetch('/admin/ai-assistant/chat', {
            method: 'POST',
            headers: {
                Accept: 'text/event-stream',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                message: content,
                persona_slug: chatPersonaId.value ? personas.value.find((p) => p.id === chatPersonaId.value)?.slug : null,
            }),
            signal: chatAbort.signal,
        });
        await readSseStream(res, (event, data) => handleChatEvent(event, data, assistantMsg));
        if (!assistantMsg.id) {
            assistantMsg.id = chatMessages.value.length + 1;
            pushChat({ role: 'assistant', content: assistantMsg.content || 'Tidak ada respon.' });
        }
    } catch (e) {
        if (e.name !== 'AbortError') {
            const msg = e?.message || 'Gagal mengirim pesan.';
            if (assistantMsg.id === null) {
                assistantMsg.content = msg;
                assistantMsg.id = chatMessages.value.length + 1;
                pushChat({ role: 'error', content: msg });
            }
        }
    } finally {
        chatTyping.value = false;
        chatBusy.value = false;
        chatAbort = null;
        nextTick(scrollChatBottom);
    }
}

async function scrollChatBottom() {
    await nextTick();
    if (chatListEl.value) chatListEl.value.scrollTop = chatListEl.value.scrollHeight;
}

async function sendChat() {
    const content = chatInput.value.trim();
    if (!content || chatBusy.value) return;
    chatInput.value = '';
    pushChat({ role: 'user', content });
    await sendChatTest(content);
}

function cancelChat() {
    chatAbort?.abort();
    chatBusy.value = false;
    chatTyping.value = false;
}

function clearChat() {
    chatMessages.value = [];
    submittedComponentsTest.clear();
}

// =====================================================================
// ACTIVITY TAB
// =====================================================================

const conversations = ref({ loading: false, items: [], error: '' });
const auditLogs = ref({ loading: false, items: [], error: '' });

async function fetchConversations() {
    conversations.value.loading = true;
    try {
        const data = await apiCall('/admin/ai-assistant/conversations', { method: 'GET' });
        conversations.value = { loading: false, items: data.conversations, error: '' };
    } catch (e) {
        conversations.value = { loading: false, items: [], error: e.message };
    }
}
async function fetchAuditLogs() {
    auditLogs.value.loading = true;
    try {
        const data = await apiCall('/admin/ai-assistant/audit-logs', { method: 'GET' });
        auditLogs.value = { loading: false, items: data.logs, error: '' };
    } catch (e) {
        auditLogs.value = { loading: false, items: [], error: e.message };
    }
}

// =====================================================================
// Lifecycle / watchers
// =====================================================================

watch(activeTab, (tab) => {
    if (tab === 'knowledge') fetchDocuments();
    if (tab === 'activity') { fetchConversations(); fetchAuditLogs(); }
    if (tab === 'chat') nextTick(scrollChatBottom);
});

watch(() => uploadForm.persona_id, () => {
    if (activeTab.value === 'knowledge') fetchDocuments();
});

function formatBytes(n) {
    if (!n) return '0 B';
    const u = ['B', 'KB', 'MB', 'GB'];
    let v = n;
    let i = 0;
    while (v >= 1024 && i < u.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(1)} ${u[i]}`;
}
function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('id-ID', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    } catch { return iso; }
}
function formatTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch { return iso; }
}

const aiTabs = [
    { key: 'personas', label: 'AI Personas', icon: 'person' },
    { key: 'tools', label: 'AI Tools', icon: 'build' },
    { key: 'knowledge', label: 'Knowledge Base (RAG)', icon: 'library_books' },
    { key: 'chat', label: 'Test Chat', icon: 'chat' },
    { key: 'activity', label: 'Aktivitas Log', icon: 'history' },
];

onMounted(() => {
    fetchDocuments();
});
onBeforeUnmount(() => {
    if (toastTimer) clearTimeout(toastTimer);
    chatAbort?.abort();
});
</script>

<template>
    <Head title="AI Assistant Control Panel" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">AI Assistant Control Panel</h1>
                    <p class="mt-1 text-on-surface-variant">Kelola AI Personas, Tools, Knowledge Base (RAG), Live Test Chat, dan Monitor Aktivitas Log In-Process.</p>
                </div>
                <div class="flex items-center gap-3">
                    <AppBadge tone="success">In-process</AppBadge>
                </div>
            </header>

            <!-- Flash success -->
            <AppCard v-if="flash">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-secondary-container text-secondary">✓</div>
                    <p class="font-bold text-primary">{{ flash.message }}</p>
                </div>
            </AppCard>

            <!-- Navigation Tabs -->
            <AppTabs
                v-model="activeTab"
                :items="aiTabs"
                variant="pills-bar"
                aria-label="Tab AI Assistant"
            />

            <!-- ============= PERSONAS TAB ============= -->
            <div v-if="activeTab === 'personas'" class="space-y-6">
                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Daftar Persona</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">System prompt + tool scope per persona.</p>
                        </div>
                        <AppButton icon="add" @click="openCreatePersona">Persona Baru</AppButton>
                    </header>

                    <div v-if="personas.length === 0" class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-10 text-center">
                        <AppIcon name="person" class="text-4xl text-on-surface-variant" />
                        <p class="mt-2 text-sm text-on-surface-variant">Belum ada persona. Buat satu untuk mulai.</p>
                    </div>
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Persona</th>
                                    <th class="px-4 py-3 text-left font-semibold">Slug</th>
                                    <th class="px-4 py-3 text-right font-semibold">Tools</th>
                                    <th class="px-4 py-3 text-center font-semibold">Status</th>
                                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="p in personas" :key="p.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-primary">{{ p.name }}</span>
                                            <AppBadge v-if="p.is_default" tone="primary">Default</AppBadge>
                                        </div>
                                        <p class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ p.system_prompt }}</p>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-on-surface-variant">{{ p.slug }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <AppBadge tone="neutral">{{ p.tools_count }}</AppBadge>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <AppBadge :tone="p.is_active ? 'success' : 'neutral'">{{ p.is_active ? 'Aktif' : 'Nonaktif' }}</AppBadge>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <AppIconButton name="edit" size="sm" tooltip="Edit" @click="openEditPersona(p)" />
                                            <AppIconButton v-if="!p.is_default" name="star" size="sm" tooltip="Set as default" @click="togglePersona(p, 'is_default')" />
                                            <AppIconButton :name="p.is_active ? 'toggle_on' : 'toggle_off'" size="sm" :tooltip="p.is_active ? 'Nonaktifkan' : 'Aktifkan'" @click="togglePersona(p, 'is_active')" />
                                            <AppIconButton v-if="!p.is_default" name="delete" size="sm" tone="danger" tooltip="Hapus" @click="deletePersona(p)" />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>

            <!-- ============= TOOLS TAB ============= -->
            <div v-else-if="activeTab === 'tools'" class="space-y-6">
                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Tool Registry & Sinkronisasi</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Tools ter-register via <code class="rounded bg-surface-container px-1 py-0.5 text-xs">ToolHandler</code>. Sync dari codebase untuk materialisasi DB row.</p>
                        </div>
                        <AppButton icon="sync" :loading="toolSyncing" @click="syncTools">Sinkronkan dari Registry</AppButton>
                    </header>

                    <div v-if="tools.length === 0" class="rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-4 py-10 text-center">
                        <AppIcon name="build" class="text-4xl text-on-surface-variant" />
                        <p class="mt-2 text-sm text-on-surface-variant">Belum ada tool di DB. Klik "Sinkronkan" untuk menarik dari registry.</p>
                    </div>
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold">Tool</th>
                                    <th class="px-4 py-3 text-center font-semibold">Registry</th>
                                    <th class="px-4 py-3 text-center font-semibold">Konfirmasi</th>
                                    <th class="px-4 py-3 text-center font-semibold">Aktif</th>
                                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="t in tools" :key="t.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-3">
                                        <p class="font-mono text-xs font-bold text-primary">{{ t.name }}</p>
                                        <p class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ t.description }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <AppBadge :tone="t.is_registered ? 'success' : 'warning'">
                                            {{ t.is_registered ? 'Terdaftar' : 'Tertinggal' }}
                                        </AppBadge>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <AppIconButton
                                            size="sm"
                                            :tone="t.requires_confirmation ? 'warning' : 'neutral'"
                                            :name="t.requires_confirmation ? 'lock' : 'lock_open'"
                                            :tooltip="t.requires_confirmation ? 'Wajib konfirmasi' : 'Tanpa konfirmasi'"
                                            @click="toggleTool(t, 'requires_confirmation')"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <AppIconButton
                                            size="sm"
                                            :tone="t.is_active ? 'success' : 'neutral'"
                                            :name="t.is_active ? 'toggle_on' : 'toggle_off'"
                                            :tooltip="t.is_active ? 'Aktif' : 'Nonaktif'"
                                            @click="toggleTool(t, 'is_active')"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <AppIconButton size="sm" name="info" tooltip="Detail" @click="openToolDetail(t)" />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>

            <!-- ============= KNOWLEDGE BASE TAB ============= -->
            <div v-else-if="activeTab === 'knowledge'" class="space-y-6">
                <AppCard>
                    <header class="mb-4">
                        <h2 class="text-lg font-bold text-primary">Upload Dokumen RAG</h2>
                        <p class="mt-0.5 text-xs text-on-surface-variant">File akan di-chunk dan di-embed untuk retrieval.</p>
                    </header>
                    <form class="space-y-4" @submit.prevent="submitUpload">
                        <AppInput v-model="uploadForm.title" label="Judul (opsional)" placeholder="Mis. Buku Pedoman Koperasi 2024" />
                        <div>
                            <SmartSelect
                                v-model="uploadForm.persona_id"
                                label="Persona (opsional)"
                                :options="uploadPersonaOptions"
                                placeholder="— Pilih persona —"
                                hint="Dokumen akan di-scope ke persona ini. Kosongkan untuk global."
                                :clearable="false"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-bold uppercase tracking-wider text-primary">File</label>
                            <label
                                class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-lowest px-4 py-6 text-center transition-colors hover:border-primary"
                                :class="uploadDragOver ? 'border-primary bg-primary/5' : ''"
                                @dragover.prevent="uploadDragOver = true"
                                @dragleave.prevent="uploadDragOver = false"
                                @drop="onUploadDrop"
                            >
                                <AppIcon name="upload_file" class="text-2xl text-on-surface-variant" />
                                <p class="text-sm text-on-surface-variant">
                                    <span class="font-semibold text-primary">Klik untuk pilih</span> atau drop file di sini
                                </p>
                                <p class="text-xs text-on-surface-variant">PDF / DOCX / MD / HTML / TXT — maks 20 MB</p>
                                <input type="file" class="hidden" accept=".pdf,.docx,.md,.markdown,.html,.htm,.txt,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown,text/html" @change="onUploadFile">
                            </label>
                            <p v-if="uploadFileName" class="mt-2 text-xs text-on-surface-variant">
                                Dipilih: <span class="font-semibold">{{ uploadFileName }}</span>
                            </p>
                        </div>
                        <p v-if="uploadResult" class="text-xs" :class="uploadResult.ok ? 'text-success' : 'text-error'">{{ uploadResult.message }}</p>
                        <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                            <AppButton type="submit" icon="upload" :loading="uploadLoading" :disabled="!uploadForm.file">Upload &amp; Ingest</AppButton>
                        </div>
                    </form>
                </AppCard>

                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Dokumen Terupload</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Total {{ docs.items.length }} dokumen aktif.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <SmartSelect v-model="uploadForm.persona_id" :options="kbPersonaOptions" placeholder="Semua persona" hide-label class="min-w-44" />
                            <AppButton type="button" variant="secondary" size="compact" icon="refresh" :loading="docs.loading" @click="fetchDocuments">
                                Refresh
                            </AppButton>
                        </div>
                    </header>

                    <div v-if="docs.loading" class="rounded-xl bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">Memuat…</div>
                    <AppCard v-else-if="docs.error" class="border-tertiary bg-tertiary-fixed/30 text-tertiary">{{ docs.error }}</AppCard>
                    <AppEmptyState v-else-if="!docs.items.length" icon="library_books" title="Belum ada dokumen" description="Upload file di atas untuk mulai membangun knowledge base." />
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Judul</th>
                                    <th class="px-4 py-2 text-left font-semibold">Persona</th>
                                    <th class="px-4 py-2 text-left font-semibold">Format</th>
                                    <th class="px-4 py-2 text-right font-semibold">Ukuran</th>
                                    <th class="px-4 py-2 text-right font-semibold">Chunks</th>
                                    <th class="px-4 py-2 text-left font-semibold">Diupload</th>
                                    <th class="px-4 py-2 text-right font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="d in docs.items" :key="d.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-2">
                                        <p class="font-semibold text-primary">{{ d.title }}</p>
                                        <p v-if="d.preview" class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ d.preview }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-on-surface-variant">{{ d.persona_name ?? 'Global' }}</td>
                                    <td class="px-4 py-2">
                                        <span class="rounded bg-surface-container-lowest px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant">{{ d.format || '—' }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ formatBytes(d.content_length) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ d.chunks_count }}</td>
                                    <td class="px-4 py-2 text-xs text-on-surface-variant">{{ formatDate(d.created_at) }}</td>
                                    <td class="px-4 py-2">
                                        <div class="flex items-center justify-end gap-1">
                                            <AppIconButton size="sm" name="visibility" tooltip="Lihat detail" @click="viewDocument(d)" />
                                            <AppIconButton size="sm" name="delete" tone="danger" tooltip="Hapus" @click="deleteDocument(d)" />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>

            <!-- ============= TEST CHAT TAB ============= -->
            <div v-else-if="activeTab === 'chat'" class="space-y-4">
                <AppCard>
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Test Chat</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Streaming SSE ke AgentLoop in-process.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <SmartSelect v-model="chatPersonaId" :options="chatPersonaOptions" hide-label placeholder="Pilih persona" class="min-w-56" />
                            <AppButton v-if="chatMessages.length" type="button" variant="secondary" size="compact" icon="delete_sweep" @click="clearChat">
                                Reset
                            </AppButton>
                        </div>
                    </header>

                    <div ref="chatListEl" class="min-h-96 max-h-[28rem] space-y-2 overflow-y-auto rounded-lg bg-surface p-3">
                        <p v-if="!chatMessages.length" class="text-center text-xs text-on-surface-variant">Belum ada percakapan.</p>
                        <div v-for="msg in chatMessages" :key="msg.id" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                            <div
                                class="max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-relaxed"
                                :class="msg.role === 'user'
                                    ? 'rounded-br-sm bg-primary text-on-primary whitespace-pre-wrap'
                                    : msg.role === 'error'
                                        ? 'rounded-bl-sm border border-error/40 bg-error-container text-error'
                                        : 'rounded-bl-sm border border-outline-variant bg-surface-container-lowest text-primary'"
                            >
                                <template v-if="msg.role === 'user' || msg.role === 'error'">{{ msg.content }}</template>
                                <div v-else class="flex flex-col gap-2">
                                    <template v-for="block in blocksFor(msg)" :key="block.id">
                                        <h1 v-if="block.type === 'heading' && block.level === 1" class="text-base font-bold">{{ block.text }}</h1>
                                        <h2 v-else-if="block.type === 'heading' && block.level === 2" class="text-sm font-bold">{{ block.text }}</h2>
                                        <h3 v-else-if="block.type === 'heading' && block.level === 3" class="text-sm font-semibold">{{ block.text }}</h3>
                                        <!-- eslint-disable-next-line vue/no-v-html -->
                                        <div v-else-if="block.type === 'paragraph' || block.type === 'code'" class="assistant-md-body" v-html="block.html" />
                                        <ArtifactCard v-else-if="block.type === 'artifact'" :block="block" @open="openArtifact(block)" />
                                        <ActionButton v-else-if="block.type === 'button'" :block="block" @submit="(payload) => onComponentSubmitTest(block, payload)" />
                                        <PollCard v-else-if="block.type === 'poll'" :block="block" :submitted="submittedComponentsTest.get(block.id) ?? null" @submit="(payload) => onComponentSubmitTest(block, payload)" />
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div v-if="chatTyping" class="flex justify-start" :aria-label="chatTypingLabel">
                            <div class="flex max-w-[85%] items-center gap-2 rounded-2xl rounded-bl-sm border border-outline-variant bg-surface-container-lowest px-3 py-2">
                                <span class="flex items-center gap-1">
                                    <span class="inline-block size-1.5 animate-bounce rounded-full bg-on-surface/40" style="animation-delay:0s" />
                                    <span class="inline-block size-1.5 animate-bounce rounded-full bg-on-surface/40" style="animation-delay:0.15s" />
                                    <span class="inline-block size-1.5 animate-bounce rounded-full bg-on-surface/40" style="animation-delay:0.3s" />
                                </span>
                                <span class="text-xs text-on-surface-variant">{{ chatTypingLabel }}</span>
                            </div>
                        </div>
                    </div>

                    <form class="mt-3 flex items-end gap-2" @submit.prevent="sendChat">
                        <textarea
                            v-model="chatInput"
                            rows="2"
                            placeholder="Tulis pertanyaan…"
                            class="min-h-12 max-h-32 flex-1 resize-none rounded-xl border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm leading-5 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:opacity-50"
                            :disabled="chatBusy"
                            @keydown.enter.exact.prevent="sendChat"
                        />
                        <AppIconButton v-if="chatBusy" name="close" size="lg" tone="secondary" filled type="button" aria-label="Batal" @click="cancelChat" />
                        <AppIconButton v-else name="send" size="lg" type="submit" aria-label="Kirim" :disabled="!chatInput.trim()" />
                    </form>
                </AppCard>
            </div>

            <!-- ============= ACTIVITY TAB ============= -->
            <div v-else-if="activeTab === 'activity'" class="space-y-6">
                <AppCard>
                    <header class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-primary">Percakapan Terbaru</h2>
                        <AppButton type="button" variant="secondary" size="compact" icon="refresh" :loading="conversations.loading" @click="fetchConversations">
                            Refresh
                        </AppButton>
                    </header>
                    <div v-if="conversations.loading" class="rounded-xl bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">Memuat…</div>
                    <AppEmptyState v-else-if="!conversations.items.length" icon="forum" title="Belum ada percakapan" />
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Persona</th>
                                    <th class="px-4 py-2 text-left font-semibold">Channel</th>
                                    <th class="px-4 py-2 text-right font-semibold">Pesan</th>
                                    <th class="px-4 py-2 text-left font-semibold">Mulai</th>
                                    <th class="px-4 py-2 text-left font-semibold">Aktivitas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="c in conversations.items" :key="c.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-2">{{ c.persona_name }}</td>
                                    <td class="px-4 py-2"><AppBadge tone="neutral">{{ c.channel }}</AppBadge></td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ c.messages_count }}</td>
                                    <td class="px-4 py-2 text-xs text-on-surface-variant">{{ formatDate(c.started_at) }}</td>
                                    <td class="px-4 py-2 text-xs text-on-surface-variant">{{ formatDate(c.last_activity_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>

                <AppCard>
                    <header class="mb-4 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-primary">Audit Log</h2>
                        <AppButton type="button" variant="secondary" size="compact" icon="refresh" :loading="auditLogs.loading" @click="fetchAuditLogs">
                            Refresh
                        </AppButton>
                    </header>
                    <div v-if="auditLogs.loading" class="rounded-xl bg-surface-container-lowest px-4 py-8 text-center text-sm text-on-surface-variant">Memuat…</div>
                    <AppEmptyState v-else-if="!auditLogs.items.length" icon="history" title="Belum ada audit log" />
                    <div v-else class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Waktu</th>
                                    <th class="px-4 py-2 text-left font-semibold">Aktor</th>
                                    <th class="px-4 py-2 text-left font-semibold">Action</th>
                                    <th class="px-4 py-2 text-left font-semibold">Entity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant bg-surface">
                                <tr v-for="log in auditLogs.items" :key="log.id" class="hover:bg-surface-container-lowest">
                                    <td class="px-4 py-2 font-mono text-xs text-on-surface-variant">{{ formatTime(log.created_at) }}</td>
                                    <td class="px-4 py-2 text-xs">{{ log.actor }}</td>
                                    <td class="px-4 py-2">
                                        <AppBadge :tone="log.action.includes('failed') || log.action.includes('error') ? 'error' : log.action.includes('executed') ? 'success' : 'neutral'">
                                            {{ log.action }}
                                        </AppBadge>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs text-on-surface-variant">{{ log.entity_type }} #{{ log.entity_id?.slice(0, 8) ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>

            <!-- Person modal -->
            <AppModal v-model="personaModal.open" :title="personaModal.mode === 'create' ? 'Buat Persona' : 'Edit Persona'" size="lg">
                <div class="space-y-4">
                    <AppInput v-model="personaModal.data.name" label="Nama" placeholder="Contoh: Koperasi Assistant" />
                    <AppInput v-model="personaModal.data.slug" label="Slug" placeholder="Contoh: koperasi-assistant (otomatis dari nama jika kosong)" />
                    <AppTextarea v-model="personaModal.data.system_prompt" label="System Prompt" placeholder="Deskripsikan kepribadian, gaya bahasa, dan batasan persona ini…" :rows="8" />
                    <div>
                        <label class="mb-2 block text-sm font-bold uppercase tracking-wider text-primary">Tool Scope (kosongkan = semua tools)</label>
                        <div class="grid max-h-56 grid-cols-1 gap-2 overflow-y-auto rounded-xl border border-outline-variant bg-surface-container-lowest p-3 sm:grid-cols-2">
                            <label v-for="t in tools" :key="t.id" class="flex items-start gap-2 rounded-lg p-2 hover:bg-surface-container-low">
                                <AppCheckbox
                                    class="mt-1"
                                    :value="t.id"
                                    :model-value="personaModal.data.tool_ids.includes(t.id)"
                                    @update:model-value="(checked) => {
                                        if (checked && !personaModal.data.tool_ids.includes(t.id)) {
                                            personaModal.data.tool_ids.push(t.id);
                                        } else if (!checked) {
                                            personaModal.data.tool_ids = personaModal.data.tool_ids.filter((id) => id !== t.id);
                                        }
                                    }"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-mono text-xs font-bold text-primary">{{ t.name }}</p>
                                    <p class="mt-0.5 line-clamp-1 text-xs text-on-surface-variant">{{ t.description }}</p>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="flex items-center gap-6">
                        <AppSwitch v-model="personaModal.data.is_active" label="Aktif" description="Persona dapat dipilih di chat" />
                        <AppSwitch v-model="personaModal.data.is_default" label="Default" description="Persona fallback" />
                    </div>
                </div>
                <template #footer>
                    <AppButton variant="ghost" @click="personaModal.open = false">Batal</AppButton>
                    <AppButton icon="save" @click="submitPersona">{{ personaModal.mode === 'create' ? 'Buat' : 'Simpan' }}</AppButton>
                </template>
            </AppModal>

            <!-- Tool detail modal -->
            <AppModal v-if="toolModal.tool" v-model="toolModal.open" :title="toolModal.tool.name" size="lg">
                <div class="space-y-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Description</p>
                        <p class="mt-1 text-sm">{{ toolModal.tool.description }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Registry</p>
                            <p class="mt-1"><AppBadge :tone="toolModal.tool.is_registered ? 'success' : 'warning'">{{ toolModal.tool.is_registered ? 'Terdaftar' : 'Tertinggal' }}</AppBadge></p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Konfirmasi</p>
                            <p class="mt-1"><AppBadge :tone="toolModal.tool.requires_confirmation ? 'warning' : 'neutral'">{{ toolModal.tool.requires_confirmation ? 'Wajib konfirmasi' : 'Otomatis' }}</AppBadge></p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">JSON Schema</p>
                        <pre class="mt-2 max-h-80 overflow-auto rounded-xl border border-outline-variant bg-surface-container-lowest p-3 font-mono text-xs">{{ JSON.stringify(toolModal.tool.json_schema, null, 2) }}</pre>
                    </div>
                </div>
                <template #footer>
                    <AppButton @click="toolModal.open = false">Tutup</AppButton>
                </template>
            </AppModal>

            <!-- Document detail modal -->
            <AppModal v-if="docModal.doc" v-model="docModal.open" :title="docModal.doc.title" size="lg">
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Format</p>
                            <p class="mt-1 font-mono">{{ docModal.doc.format }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Persona</p>
                            <p class="mt-1">{{ docModal.doc.persona_name ?? 'Global' }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Chunks ({{ docModal.doc.chunks.length }})</p>
                        <div class="mt-2 max-h-96 space-y-2 overflow-y-auto rounded-xl border border-outline-variant bg-surface-container-lowest p-3">
                            <div v-for="chunk in docModal.doc.chunks" :key="chunk.id" class="rounded-lg border border-outline-variant bg-surface p-3">
                                <div class="flex items-center justify-between text-xs text-on-surface-variant">
                                    <span>Chunk #{{ chunk.chunk_index }}</span>
                                    <AppBadge :tone="chunk.has_embedding ? 'success' : 'warning'">{{ chunk.has_embedding ? 'Embedded' : 'Pending' }}</AppBadge>
                                </div>
                                <p class="mt-2 whitespace-pre-wrap text-xs">{{ chunk.chunk_text }}</p>
                            </div>
                        </div>
                    </div>
                </div>
                <template #footer>
                    <AppButton @click="docModal.open = false">Tutup</AppButton>
                </template>
            </AppModal>

            <!-- Artifact modal -->
            <ArtifactModal :block="activeArtifactTest" @close="activeArtifactTest = null" />

            <!-- Toast -->
            <Transition name="fade">
                <div v-if="toast" class="fixed bottom-6 right-6 z-50 max-w-sm rounded-xl border p-4 shadow-lg"
                    :class="toast.tone === 'error' ? 'border-error/30 bg-error-container text-error' : 'border-secondary/30 bg-secondary-container text-secondary'">
                    <p class="text-sm font-semibold">{{ toast.message }}</p>
                </div>
            </Transition>
        </div>
    </AdminLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 200ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>



