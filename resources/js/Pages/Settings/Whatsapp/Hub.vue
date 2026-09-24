<script setup>
import { useCan } from '../../../composables/useCan';
import { useConfirm } from '../../../composables/useConfirm';
import { useMoney } from '../../../composables/useMoney';
import { useToast } from '../../../composables/useToast';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppRadioGroup from '../../../Components/AppRadioGroup.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AuthenticatedLayout from '../../../Layouts/TenantAdminLayout.vue';

const props = defineProps({
    instances: { type: Array, default: () => [] },
    global: { type: Object, default: () => ({ enabled: false, configured: false }) },
    baseUrl: { type: String, default: '' },
    due_date: { type: String, default: null },
    items: { type: Array, default: () => [] },
    billing_gateway: { type: Object, default: null },
    totals: { type: Object, default: null },
});

const { confirm: confirmAction } = useConfirm();
const { can } = useCan();
const { money } = useMoney();
const toast = useToast();
const loadingAction = ref(null);

const tabs = computed(() => [
    ...(can('settings.manage') ? [{ key: 'instances', label: 'Status & Instance', icon: 'settings' }] : []),
    ...(can('settings.manage') ? [{ key: 'templates', label: 'Template Pesan', icon: 'edit_note' }] : []),
    ...(can('messages.send') ? [{ key: 'billing', label: 'Kirim Tagihan', icon: 'send' }] : []),
]);
const activeTab = ref(getInitialTab());

function getInitialTab() {
    const requested = new URL(window.location.href).searchParams.get('tab');
    if (requested && tabs.value.some((tab) => tab.key === requested)) return requested;
    return tabs.value[0]?.key ?? 'instances';
}

function go(tab) {
    router.get('/settings/whatsapp', { tab }, { preserveState: true, preserveScroll: true });
}

watch(tabs, () => {
    if (!tabs.value.some((tab) => tab.key === activeTab.value)) {
        activeTab.value = tabs.value[0]?.key ?? '';
    }
});

const stats = computed(() => ({
    total: props.instances.length,
    active: props.instances.filter((i) => i.is_active).length,
    connected: props.instances.filter((i) => ['open', 'connected'].includes((i.status || '').toLowerCase())).length,
}));

const showCreateModal = ref(false);
const editingInstance = ref(null);

const form = useForm({
    name: '',
    phone_number: '',
    is_default: false,
    is_active: true,
    daily_limit: 0,
});

const globalForm = useForm({
    template_billing: props.global.template_billing ?? '',
    template_installment: props.global.template_installment ?? '',
    is_enabled: props.global.enabled ?? false,
    rotation_mode: props.global.rotation_mode ?? 'round_robin',
});

const rotationOptions = [
    { value: 'round_robin', label: 'Round Robin', icon: 'shuffle' },
    { value: 'default_only', label: 'Nomor Utama', icon: 'star' },
];

const showQrModal = ref(false);
const qrInstance = ref(null);
const qrCode = ref(null);
const qrStatus = ref('idle');
const qrMessage = ref('');
let pollTimer = null;

const showTestModal = ref(false);
const testInstance = ref(null);
const testForm = useForm({ phone: '', message: '' });

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
        },
        credentials: 'same-origin',
    });

    return await response.json();
}

function openCreate() {
    form.reset();
    form.clearErrors();
    showCreateModal.value = true;
}

function openEdit(instance) {
    editingInstance.value = instance;
    form.name = instance.name;
    form.phone_number = instance.phone_number ?? '';
    form.is_default = Boolean(instance.is_default);
    form.is_active = Boolean(instance.is_active);
    form.daily_limit = instance.daily_limit ?? 0;
    form.clearErrors();
    showCreateModal.value = true;
}

function closeForm() {
    showCreateModal.value = false;
    editingInstance.value = null;
    form.reset();
    form.clearErrors();
}

function submitInstance() {
    if (editingInstance.value) {
        form.put(`/settings/whatsapp/instances/${editingInstance.value.row_id}`, {
            preserveScroll: true,
            onSuccess: closeForm,
        });
        return;
    }

    form.post('/settings/whatsapp/instances', {
        preserveScroll: true,
        onSuccess: closeForm,
    });
}

async function toggleDefault(instance) {
    if (!instance.is_default && !await confirmAction({
        title: 'Set Nomor Utama',
        message: `Jadikan "${instance.name}" sebagai nomor utama?`,
        confirmLabel: 'Jadikan Utama',
    })) return;

    const form = useForm({ ...instance, is_default: !instance.is_default });
    form.put(`/settings/whatsapp/instances/${instance.row_id}`, { preserveScroll: true });
}

async function toggleActive(instance) {
    const form = useForm({ ...instance, is_active: !instance.is_active });
    form.put(`/settings/whatsapp/instances/${instance.row_id}`, { preserveScroll: true });
}

async function destroyInstance(instance) {
    if (!await confirmAction({
        title: 'Hapus Instance',
        message: `Session "${instance.name}" akan dihapus dari gateway dan data instance akan dihapus.`,
        confirmLabel: 'Hapus',
        variant: 'danger',
        icon: 'delete',
    })) return;

    useForm({}).delete(`/settings/whatsapp/instances/${instance.row_id}`, { preserveScroll: true });
}

async function createSession(instance) {
    loadingAction.value = instance.row_id;
    try {
        const res = await requestJson(`/settings/whatsapp/instances/${instance.row_id}/create-session`, { method: 'POST' });
        toast.success(res.message || 'Berhasil');
        if (res.success) openQr(instance, res);
    } catch (e) {
        toast.error(e?.message || 'Gagal membuat sesi gateway.');
    } finally {
        loadingAction.value = null;
    }
}

async function deleteSession(instance) {
    if (!await confirmAction({
        title: 'Hapus Session Gateway',
        message: `Hapus session WhatsApp "${instance.name}" dari gateway?`,
        confirmLabel: 'Hapus Session',
        variant: 'danger',
        icon: 'delete',
    })) return;

    loadingAction.value = instance.row_id;
    try {
        const res = await requestJson(`/settings/whatsapp/instances/${instance.row_id}/delete-session`, { method: 'DELETE' });
        toast.success(res.message || 'Session dihapus');
    } catch (e) {
        toast.error(e?.message || 'Gagal menghapus sesi gateway.');
    } finally {
        loadingAction.value = null;
    }
}

async function openQr(instance, response = null) {
    qrInstance.value = instance;
    qrCode.value = response?.qr ?? null;
    qrStatus.value = response?.state ?? instance.status ?? 'connecting';
    qrMessage.value = response?.message ?? 'Menghubungkan instance...';
    showQrModal.value = true;
    await refreshState(instance);
    startPolling(instance);
}

async function refreshState(instance) {
    if (!qrInstance.value || qrInstance.value.row_id !== instance.row_id) return;
    try {
        const res = await requestJson(`/settings/whatsapp/instances/${instance.row_id}/state`);
        qrCode.value = res.qr ?? null;
        qrStatus.value = res.state ?? res.status ?? 'unknown';
        qrMessage.value = res.message ?? '';
        if (['open', 'connected'].includes(qrStatus.value.toLowerCase())) stopPolling();
    } catch {
        qrMessage.value = 'Gagal memeriksa status instance.';
    }
}

function startPolling(instance) {
    stopPolling();
    pollTimer = setInterval(() => refreshState(instance), 3000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function closeQr() {
    stopPolling();
    showQrModal.value = false;
    qrInstance.value = null;
    qrCode.value = null;
    qrStatus.value = 'idle';
}

async function openTest(instance) {
    testInstance.value = instance;
    testForm.reset();
    testForm.clearErrors();
    showTestModal.value = true;
}

async function sendTest() {
    testForm.phone = testForm.phone.trim();
    if (!testForm.phone) return;
    loadingAction.value = `test-${testInstance.value?.row_id}`;
    try {
        const res = await requestJson(`/settings/whatsapp/instances/${testInstance.value.row_id}/test`, {
            method: 'POST',
            body: JSON.stringify({ phone: testForm.phone, message: testForm.message }),
        });
        if (res?.success) {
            toast.success(res.message || 'Pesan tes terkirim');
            showTestModal.value = false;
        } else {
            toast.error(res?.message || 'Pesan tes gagal');
        }
    } catch (e) {
        toast.error(e?.message || 'Gagal mengirim pesan tes.');
    } finally {
        loadingAction.value = null;
    }
}

function submitGlobal() {
    globalForm.put('/settings/whatsapp/global', { preserveScroll: true });
}

function statusTone(status) {
    const value = (status || '').toLowerCase();
    if (['open', 'connected'].includes(value)) return 'success';
    if (['connecting', 'pending'].includes(value)) return 'primary';
    if (['unconfigured', 'missing', 'close', 'closed'].includes(value)) return 'warning';
    return 'neutral';
}

function statusLabel(status) {
    const value = (status || '').toLowerCase();
    if (['open', 'connected'].includes(value)) return 'Terhubung';
    if (['connecting', 'pending'].includes(value)) return 'Menghubungkan';
    if (['missing'].includes(value)) return 'Belum Dibuat';
    if (['close', 'closed'].includes(value)) return 'Terputus';
    if (['unconfigured'].includes(value)) return 'Gateway Belum Siap';
    return status || 'Tidak Diketahui';
}

const qrTone = computed(() => statusTone(qrStatus.value));

const selectedDate = ref(props.due_date ?? '');
const syncingDate = ref(false);
const selected = reactive({});

function hydrateSelection() {
    Object.keys(selected).forEach((key) => delete selected[key]);
    for (const item of props.items) {
        selected[item.installment_row_id] = Boolean(item.can_send);
    }
}
hydrateSelection();

watch(
    () => [props.due_date, props.items],
    () => {
        syncingDate.value = true;
        selectedDate.value = props.due_date;
        hydrateSelection();
        queueMicrotask(() => {
            syncingDate.value = false;
        });
    },
    { deep: true },
);

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function goDate(value) {
    if (!value || value === props.due_date) return;
    router.get('/settings/whatsapp', { tab: 'billing', due_date: value }, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
}

watch(selectedDate, (value) => {
    if (!syncingDate.value && value && value !== props.due_date) goDate(value);
});

const selectedIds = computed(() =>
    props.items
        .filter((item) => selected[item.installment_row_id])
        .map((item) => item.installment_row_id),
);

const selectedAmount = computed(() =>
    props.items
        .filter((item) => selected[item.installment_row_id])
        .reduce((sum, item) => sum + Number(item.amount || 0), 0),
);

function selectSendable() {
    for (const item of props.items) {
        selected[item.installment_row_id] = Boolean(item.can_send);
    }
}

function clearSelection() {
    for (const item of props.items) {
        selected[item.installment_row_id] = false;
    }
}

const billingForm = useForm({
    due_date: props.due_date,
    installment_row_ids: [],
});

const gatewayOk = computed(() => props.billing_gateway?.configured && props.billing_gateway?.enabled);
const sourceLabel = {
    group: 'Kelompok',
    member: 'Anggota',
    beneficiary: 'Pemanfaat',
    none: '—',
};
const sourceTone = {
    group: 'primary-soft',
    member: 'info-soft',
    beneficiary: 'success-soft',
    none: 'neutral',
};

/* -------------------------------------------------------------------------- */
/* Filter & grouping                                                          */
/* -------------------------------------------------------------------------- */
const billingFilter = ref('all');
const billingSearch = ref('');
const previewType = ref('group');

const filterItems = computed(() => {
    const sendable = props.items.filter((i) => i.can_send).length;
    const groups = props.items.filter((i) => i.phone_source === 'group').length;
    const individuals = props.items.filter((i) => i.phone_source !== 'group' && i.phone_source !== 'none').length;
    const noPhone = props.items.filter((i) => !i.can_send).length;
    const items = [
        { value: 'all', label: 'Semua', count: props.items.length },
        { value: 'sendable', label: 'Ber-nomor', count: sendable },
        { value: 'group', label: 'Kelompok', count: groups },
        { value: 'individual', label: 'Individu', count: individuals },
        { value: 'no_phone', label: 'Tanpa No', count: noPhone },
    ];
    // Sembunyikan filter yang tidak punya data, tapi selalu pertahankan 'all'
    return items.filter((i) => i.value === 'all' || i.count > 0);
});

const filteredItems = computed(() => {
    const term = billingSearch.value.trim().toLowerCase();
    return props.items.filter((item) => {
        if (billingFilter.value === 'sendable' && !item.can_send) return false;
        if (billingFilter.value === 'group' && item.phone_source !== 'group') return false;
        if (billingFilter.value === 'individual' && (item.phone_source === 'group' || item.phone_source === 'none')) return false;
        if (billingFilter.value === 'no_phone' && item.can_send) return false;
        if (!term) return true;
        return (
            (item.borrower || '').toLowerCase().includes(term) ||
            (item.loan_number || '').toLowerCase().includes(term) ||
            (item.phone || '').toLowerCase().includes(term)
        );
    });
});

const isAllFilteredSelected = computed(() =>
    filteredItems.value.length > 0 && filteredItems.value.every((item) => selected[item.installment_row_id]),
);

const filterGridCols = computed(() => {
    const n = filterItems.value.length;
    if (n <= 1) return 'grid-cols-2 sm:grid-cols-1';
    if (n === 2) return 'grid-cols-2';
    if (n === 3) return 'grid-cols-2 sm:grid-cols-3';
    if (n === 4) return 'grid-cols-2 sm:grid-cols-4';
    return 'grid-cols-2 sm:grid-cols-5';
});

function toggleSelectAll() {
    if (isAllFilteredSelected.value) {
        for (const item of filteredItems.value) selected[item.installment_row_id] = false;
        return;
    }
    for (const item of filteredItems.value) {
        if (item.can_send) selected[item.installment_row_id] = true;
    }
}

/* -------------------------------------------------------------------------- */
/* Pesan preview (kelompok vs individu)                                       */
/* -------------------------------------------------------------------------- */
function previewItem() {
    return (
        props.items.find((i) => selected[i.installment_row_id] && i.can_send) ||
        props.items.find((i) => i.can_send) ||
        props.items[0]
    );
}

function previewMessageFor(source) {
    const item = previewItem();
    const template = props.global?.template_billing || '';
    if (!template) {
        return source === 'group'
            ? 'Yth. Bapak/Ibu Ketua Kelompok {nama}, tagihan angsuran ke-{angsuran_ke} pinjaman {pinjaman} sebesar Rp {total} jatuh tempo {tanggal}. Mohon diselesaikan. Terima kasih.'
            : 'Yth. Bapak/Ibu {nama}, tagihan angsuran ke-{angsuran_ke} pinjaman {pinjaman} sebesar Rp {total} jatuh tempo {tanggal}. Mohon diselesaikan. Terima kasih.';
    }
    if (!item) return template;
    const sample = source === 'group'
        ? { ...item, borrower: item.borrower?.includes('Kelompok') || !item.borrower ? 'Mawar Jaya' : item.borrower }
        : { ...item, borrower: item.borrower?.includes('Kelompok') ? 'Sumiati' : item.borrower };
    return template
        .replaceAll('{nama}', sample.borrower || '—')
        .replaceAll('{angsuran_ke}', String(sample.installment_number ?? '—'))
        .replaceAll('{total}', formatMoney(sample.amount))
        .replaceAll('{pokok}', formatMoney(sample.principal))
        .replaceAll('{jasa}', formatMoney(sample.interest))
        .replaceAll('{denda}', formatMoney(sample.penalty))
        .replaceAll('{tanggal}', formatDate(sample.due_date || props.due_date))
        .replaceAll('{pinjaman}', sample.loan_number || `#${sample.loan_row_id}`);
}

function formatMoney(value) {
    try {
        return money(value);
    } catch {
        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
    }
}

const previewGroup = computed(() => previewMessageFor('group'));
const previewIndividual = computed(() => previewMessageFor('individual'));

const lastResult = ref(null);

const sendingProgress = ref(null);
let progressResetTimer = null;

onBeforeUnmount(() => {
    stopPolling();
    if (progressResetTimer) clearTimeout(progressResetTimer);
});
async function sendBillingWithProgress() {
    billingForm.due_date = props.due_date;
    billingForm.installment_row_ids = selectedIds.value;
    if (billingForm.installment_row_ids.length === 0) return;
    const total = billingForm.installment_row_ids.length;
    sendingProgress.value = { sent: 0, total };
    billingForm.post('/notifications/billing/send', {
        preserveScroll: true,
        onSuccess: (page) => {
            const flash = page.props?.flash;
            if (flash?.billing_result) lastResult.value = flash.billing_result;
            sendingProgress.value = { sent: total, total };
            if (progressResetTimer) clearTimeout(progressResetTimer);
            progressResetTimer = setTimeout(() => { sendingProgress.value = null; progressResetTimer = null; }, 800);
            lastResult.value = lastResult.value || { sent: 0, failed: 0, skipped: 0 };
        },
        onError: () => {
            sendingProgress.value = null;
        },
    });
}
</script>

<template>
    <Head title="WhatsApp Gateway" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="mb-1 flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-on-surface-variant">
                        <AppIcon name="chat" class="text-primary" />
                        Pengaturan
                    </p>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">WhatsApp Gateway</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Kelola beberapa nomor WhatsApp per lembaga dan distribusikan pengiriman pesan untuk mengurangi risiko blokir.
                    </p>
                </div>
            </header>

            <AppTabs
                v-model="activeTab"
                :items="tabs"
                variant="pills-bar"
                aria-label="Tab WhatsApp Hub"
                @update:model-value="go($event)"
            />

            <template v-if="activeTab === 'instances'">
                <div class="flex justify-end">
                    <AppButton type="button" icon="add" :disabled="!props.global.configured" @click="openCreate">Tambah Instance</AppButton>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                        <AppIcon name="smartphone" tone="primary" :container-size="11" />
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Instance</p>
                            <p class="mt-0.5 text-2xl font-bold text-primary">{{ stats.total }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                        <AppIcon name="check_circle" tone="secondary" :container-size="11" />
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Instance Aktif</p>
                            <p class="mt-0.5 text-2xl font-bold text-primary">{{ stats.active }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                        <AppIcon name="link" tone="info" :container-size="11" />
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Terhubung</p>
                            <p class="mt-0.5 text-2xl font-bold text-primary">{{ stats.connected }}</p>
                        </div>
                    </div>
                </div>

                <AppCard bordered>
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Daftar Instance WhatsApp</h2>
                            <p class="mt-1 text-sm text-on-surface-variant">
                                Kelola beberapa nomor WhatsApp per lembaga dan distribusikan pengiriman pesan.
                            </p>
                        </div>
                        <AppBadge :tone="props.global.configured ? 'success' : 'warning'">
                            {{ props.global.configured ? 'Gateway Siap' : 'Gateway Belum Dikonfigurasi' }}
                        </AppBadge>
                    </div>

                    <div v-if="props.instances.length" class="overflow-x-auto">
                        <table class="w-full min-w-[40rem] text-left text-[11px] sm:min-w-[56rem] sm:text-sm">
                            <thead class="bg-surface-container-low text-sm">
                                <tr>
                                    <th class="px-4 py-3 font-bold text-primary">Instance</th>
                                    <th class="px-4 py-3 font-bold text-primary">Status</th>
                                    <th class="px-4 py-3 font-bold text-primary">Nomor</th>
                                    <th class="px-4 py-3 font-bold text-primary">Batas Harian</th>
                                    <th class="px-4 py-3 font-bold text-primary">Mode</th>
                                    <th class="px-4 py-3 text-right font-bold text-primary">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="instance in props.instances" :key="instance.row_id" class="border-t border-outline-variant align-top">
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-primary">{{ instance.name }}</div>
                                        <div class="mt-1 font-mono text-xs text-on-surface-variant">{{ instance.instance_name }}</div>
                                    </td>
                                    <td class="px-4 py-4"><AppBadge :tone="statusTone(instance.status)">{{ statusLabel(instance.status) }}</AppBadge></td>
                                    <td class="px-4 py-4 text-on-surface-variant">{{ instance.phone_number || 'Belum tersambung' }}</td>
                                    <td class="px-4 py-4 text-on-surface-variant">{{ instance.daily_limit ? instance.daily_limit.toLocaleString('id-ID') : 'Tanpa batas' }}</td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-col gap-2">
                                            <div class="flex items-center gap-2">
                                                <AppCheckbox :model-value="instance.is_default" @update:model-value="toggleDefault(instance)" />
                                                <span class="text-sm text-on-surface-variant">Default</span>
                                            </div>
                                            <AppSwitch :model-value="instance.is_active" @update:model-value="toggleActive(instance)" />
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <AppButton size="compact" variant="secondary" icon="qr_code_scanner" :loading="loadingAction === instance.row_id" :disabled="!props.global.configured" @click="createSession(instance)">Buat QR</AppButton>
                                            <AppButton size="compact" variant="ghost" icon="delete" :loading="loadingAction === instance.row_id" :disabled="!props.global.configured" @click="deleteSession(instance)">Hapus Sesi</AppButton>
                                            <AppButton size="compact" variant="ghost" icon="visibility" @click="openQr(instance)">Status</AppButton>
                                            <AppButton size="compact" variant="ghost" icon="send" :disabled="!props.global.configured" @click="openTest(instance)">Tes</AppButton>
                                            <AppButton size="compact" variant="ghost" icon="edit" @click="openEdit(instance)">Edit</AppButton>
                                            <AppButton size="compact" variant="danger" icon="delete" @click="destroyInstance(instance)">Hapus</AppButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <AppEmptyState
                        v-else
                        icon="chat"
                        title="Belum ada instance WhatsApp"
                        description="Tambahkan instance pertama, lalu buat QR untuk memasangkan nomor WhatsApp."
                    />
                </AppCard>
            </template>

            <AppCard v-if="activeTab === 'templates'" bordered>
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Template & Aktivasi</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Template dan strategi pengiriman berlaku untuk semua notifikasi WhatsApp.</p>
                    </div>
                    <AppIcon name="edit_note" class="hidden text-3xl text-primary sm:block" />
                </div>
                <form class="space-y-6" @submit.prevent="submitGlobal">
                    <section>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Strategi Pengiriman</h3>
                        <AppRadioGroup v-model="globalForm.rotation_mode" label="Rotasi Nomor" :options="rotationOptions" />
                    </section>

                    <section>
                        <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-on-surface-variant">Template Pesan</h3>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppTextarea v-model="globalForm.template_billing" label="Pesan Tagihan" :rows="5" hint="Placeholder: {nama}, {angsuran_ke}, {total}, {tanggal}, {pinjaman}" />
                            <AppTextarea v-model="globalForm.template_installment" label="Pesan Angsuran" :rows="5" hint="Placeholder: {nama}, {penyetor}, {angsuran_ke}, {total}" />
                        </div>
                    </section>

                    <section>
                        <div class="flex items-center justify-between rounded-xl border border-outline-variant bg-surface-container-low px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-primary">Aktifkan Gateway</p>
                                <p class="mt-0.5 text-xs text-on-surface-variant">Jika nonaktif, seluruh pengiriman WhatsApp akan diblokir.</p>
                            </div>
                            <AppSwitch v-model="globalForm.is_enabled" />
                        </div>
                    </section>

                    <div class="flex flex-wrap justify-end gap-2 border-t border-outline-variant pt-4">
                        <AppButton type="submit" icon="save" :loading="globalForm.processing">Simpan Pengaturan</AppButton>
                    </div>
                </form>
            </AppCard>

        <AppModal v-model="showCreateModal" :title="editingInstance ? 'Edit Instance' : 'Tambah Instance'" size="md">
            <form class="space-y-5" @submit.prevent="submitInstance">
                <AppInput v-model="form.name" label="Nama Instance" required placeholder="Contoh: Customer Service 1" :error="form.errors.name" />
                <AppInput v-model="form.phone_number" label="Nomor WhatsApp (opsional)" placeholder="08xxxxxxxxxx" :error="form.errors.phone_number" hint="Boleh diisi setelah nomor tersambung." />
                <AppInput v-model.number="form.daily_limit" label="Batas Pesan Harian" type="number" min="0" :error="form.errors.daily_limit" hint="Isi 0 untuk tanpa batas." />
                <div class="grid gap-3 rounded-xl border border-outline-variant bg-surface-container-low p-4 sm:grid-cols-2">
                    <AppCheckbox v-model="form.is_default" label="Jadikan nomor utama" />
                    <AppCheckbox v-model="form.is_active" label="Instance aktif" />
                </div>
            </form>
            <template #footer>
                <AppButton variant="ghost" @click="closeForm">Batal</AppButton>
                <AppButton :loading="form.processing" @click="submitInstance">{{ editingInstance ? 'Simpan' : 'Tambah' }}</AppButton>
            </template>
        </AppModal>

        <div v-if="activeTab === 'billing'" class="space-y-6">
            <!-- Banner gateway -->
            <div
                v-if="!gatewayOk"
                class="flex flex-wrap items-center gap-3 rounded-xl border border-warning/40 bg-warning-container/40 px-4 py-3"
            >
                <AppIcon name="warning" class="shrink-0 text-warning" />
                <p class="min-w-0 flex-1 text-sm font-semibold text-primary">
                    Gateway belum siap. Atur di tab
                    <button class="underline" @click="go('instances')">Status &amp; Instance</button>.
                </p>
                <AppBadge tone="warning">{{ props.billing_gateway?.configured ? 'Nonaktif' : 'Belum Diatur' }}</AppBadge>
            </div>

            <!-- Banner hasil kirim -->
            <div
                v-if="lastResult"
                class="flex flex-wrap items-center gap-3 rounded-xl border border-success/40 bg-success-container/40 px-4 py-3"
            >
                <AppIcon name="task_alt" class="shrink-0 text-success" />
                <p class="min-w-0 flex-1 text-sm font-semibold text-primary">
                    <span class="text-success">{{ lastResult.sent ?? 0 }}</span> terkirim ·
                    <span class="text-error">{{ lastResult.failed ?? 0 }}</span> gagal ·
                    {{ lastResult.skipped ?? 0 }} dilewati
                </p>
                <button class="text-on-surface-variant hover:text-primary" @click="lastResult = null">
                    <AppIcon name="close" />
                </button>
            </div>

            <!-- Ringkasan metrik (single bar ringkas) -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex items-center gap-3 rounded-xl bg-surface-container-lowest p-4 ring-1 ring-outline-variant">
                    <AppIcon name="event" tone="info" :container-size="10" />
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase text-on-surface-variant">Tanggal</p>
                        <p class="truncate text-sm font-bold text-primary">{{ formatDate(props.due_date) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-surface-container-lowest p-4 ring-1 ring-outline-variant">
                    <AppIcon name="payments" tone="primary" :container-size="10" />
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase text-on-surface-variant">Total Tagihan</p>
                        <p class="truncate text-sm font-bold text-primary">{{ money(props.totals?.amount) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-surface-container-lowest p-4 ring-1 ring-outline-variant">
                    <AppIcon name="checklist" tone="secondary" :container-size="10" />
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase text-on-surface-variant">Dipilih</p>
                        <p class="truncate text-sm font-bold text-primary">{{ selectedIds.length }} · {{ money(selectedAmount) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-xl bg-surface-container-lowest p-4 ring-1 ring-outline-variant">
                    <AppIcon name="hub" tone="info" :container-size="10" />
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase text-on-surface-variant">Gateway</p>
                        <p class="truncate text-sm font-bold text-primary">
                            {{ props.billing_gateway?.instance ?? '—' }}
                            <span v-if="props.billing_gateway?.state" class="ml-1 text-xs font-normal text-on-surface-variant">
                                ({{ statusLabel(props.billing_gateway.state) }})
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Layout 2 kolom -->
            <div class="grid gap-6 xl:grid-cols-[20rem_1fr]">
                <!-- Kolom kiri -->
                <div class="space-y-6">
                    <AppCard bordered>
                        <h2 class="mb-4 flex items-center gap-2 text-sm font-bold text-primary">
                            <AppIcon name="filter_alt" class="text-base" />
                            Filter
                        </h2>
                        <div class="space-y-4">
                            <AppDatePicker v-model="selectedDate" label="Tanggal" />
                            <AppInput v-model="billingSearch" label="Pencarian" hide-label placeholder="Cari nama / nomor" icon="search" />
                            <div class="grid gap-1.5 rounded-xl bg-outline-variant/40 p-1.5" :class="filterGridCols">
                                <button
                                    v-for="item in filterItems"
                                    :key="item.value"
                                    type="button"
                                    :aria-pressed="billingFilter === item.value"
                                    class="flex flex-col items-center justify-center gap-0.5 rounded-lg px-2 py-2 text-[11px] font-bold transition-all active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                    :class="billingFilter === item.value
                                        ? 'bg-surface-container-highest text-primary shadow-sm'
                                        : 'text-on-surface-variant hover:bg-surface-container-lowest/60'"
                                    @click="billingFilter = item.value"
                                >
                                    <span class="leading-none">{{ item.label }}</span>
                                    <span class="text-[10px] font-semibold opacity-80 tabular-nums">{{ item.count }}</span>
                                </button>
                            </div>
                        </div>
                    </AppCard>

                    <AppCard bordered>
                        <div class="mb-3 flex items-center gap-2">
                            <h2 class="flex-1 text-sm font-bold text-primary">Preview Pesan</h2>
                            <div class="flex rounded-md bg-surface-container-low p-0.5">
                                <button
                                    type="button"
                                    class="rounded px-2.5 py-1 text-[11px] font-bold transition-all"
                                    :class="previewType === 'group' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'"
                                    @click="previewType = 'group'"
                                >Kelompok</button>
                                <button
                                    type="button"
                                    class="rounded px-2.5 py-1 text-[11px] font-bold transition-all"
                                    :class="previewType === 'individual' ? 'bg-primary text-on-primary' : 'text-on-surface-variant'"
                                    @click="previewType = 'individual'"
                                >Individu</button>
                            </div>
                        </div>
                        <div class="rounded-lg bg-[#d9fdd3] p-3 text-sm leading-relaxed text-[#111b21] ring-1 ring-success/30">
                            {{ previewType === 'group' ? previewGroup : previewIndividual }}
                        </div>
                    </AppCard>
                </div>

                <!-- Kolom kanan: tabel -->
                <AppCard :padded="false">
                    <header class="flex flex-wrap items-center gap-2 border-b border-outline-variant px-4 py-3">
                        <div class="min-w-0 flex-1 basis-full sm:basis-auto">
                            <h2 class="text-sm font-bold text-primary">Pinjaman Jatuh Tempo</h2>
                            <p class="text-xs text-on-surface-variant">
                                {{ filteredItems.length }} baris · {{ filteredItems.filter((i) => i.can_send).length }} siap kirim
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <AppButton size="compact" variant="ghost" icon="done_all" @click="selectSendable">Ber-nomor</AppButton>
                            <AppButton size="compact" variant="ghost" icon="clear_all" @click="clearSelection">Reset</AppButton>
                        </div>
                    </header>

                    <div v-if="props.items.length === 0" class="p-8">
                        <AppEmptyState icon="event_available" title="Tidak ada jatuh tempo" />
                    </div>

                    <div v-else-if="filteredItems.length === 0" class="p-8">
                        <AppEmptyState icon="search_off" title="Tidak ada yang cocok" />
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full min-w-[40rem] text-left text-sm">
                            <thead class="bg-surface-container-low text-[11px] uppercase tracking-wider text-on-surface-variant">
                                <tr>
                                    <th class="w-10 px-3 py-2">
                                        <AppCheckbox
                                            :model-value="isAllFilteredSelected"
                                            :indeterminate="!isAllFilteredSelected && filteredItems.some((i) => selected[i.installment_row_id])"
                                            :disabled="filteredItems.filter((i) => i.can_send).length === 0"
                                            @update:model-value="toggleSelectAll"
                                        />
                                    </th>
                                    <th class="px-3 py-2 font-bold">Peminjam</th>
                                    <th class="px-3 py-2 font-bold">Ke</th>
                                    <th class="px-3 py-2 text-right font-bold">Tagihan</th>
                                    <th class="px-3 py-2 font-bold">Tujuan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in filteredItems"
                                    :key="item.installment_row_id"
                                    class="border-t border-outline-variant hover:bg-surface-container-low/40"
                                    :class="!item.can_send && 'opacity-50'"
                                >
                                    <td class="px-3 py-2">
                                        <AppCheckbox v-model="selected[item.installment_row_id]" :disabled="!item.can_send" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <p class="truncate font-semibold text-primary">{{ item.borrower }}</p>
                                        <p class="truncate font-mono text-[11px] text-on-surface-variant">
                                            {{ item.loan_number || `#${item.loan_row_id}` }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-primary">{{ item.installment_number }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <p class="font-bold tabular-nums text-primary">{{ money(item.amount) }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div v-if="item.phone" class="flex items-center gap-1.5">
                                            <span class="font-mono text-xs text-primary">{{ item.phone }}</span>
                                            <AppBadge :tone="sourceTone[item.phone_source] || 'neutral'">
                                                {{ sourceLabel[item.phone_source] || item.phone_source }}
                                            </AppBadge>
                                        </div>
                                        <span v-else class="text-xs text-on-surface-variant">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer sticky: tombol kirim -->
                    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant bg-surface-container-lowest px-4 py-3">
                        <p class="min-w-0 flex-1 text-sm font-semibold text-primary">
                            {{ selectedIds.length }} dipilih · {{ money(selectedAmount) }}
                        </p>
                        <AppButton
                            icon="send"
                            :loading="billingForm.processing || !!sendingProgress"
                            :disabled="!gatewayOk || selectedIds.length === 0 || billingForm.processing"
                            @click="sendBillingWithProgress"
                        >
                            Kirim
                        </AppButton>
                    </footer>

                    <div v-if="sendingProgress" class="h-1 w-full overflow-hidden bg-surface-container-lowest">
                        <div class="h-full bg-primary transition-all" :style="{ width: ((sendingProgress.sent / sendingProgress.total) * 100) + '%' }"></div>
                    </div>
                </AppCard>
            </div>
        </div>

        <AppModal v-model="showQrModal" :title="qrInstance ? `Status ${qrInstance.name}` : 'Status Instance'" size="sm">
            <div class="space-y-4 text-center">
                <AppBadge :tone="qrTone">{{ statusLabel(qrStatus) }}</AppBadge>
                <div v-if="qrCode" class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                    <img :src="qrCode" alt="QR Code WhatsApp" class="mx-auto size-64 object-contain" />
                    <p class="mt-2 animate-pulse text-xs text-on-surface-variant">Scan QR menggunakan WhatsApp di HP Anda.</p>
                </div>
                <p class="text-sm text-on-surface-variant">{{ qrMessage }}</p>
            </div>
            <template #footer>
                <AppButton variant="ghost" @click="closeQr">Tutup</AppButton>
                <AppButton v-if="qrInstance" variant="secondary" icon="refresh" @click="refreshState(qrInstance)">Periksa Ulang</AppButton>
            </template>
        </AppModal>

        <AppModal v-model="showTestModal" title="Tes Pengiriman WhatsApp" size="sm">
            <form class="space-y-5" @submit.prevent="sendTest">
                <AppInput v-model="testForm.phone" label="Nomor Tujuan" required placeholder="08xxxxxxxxxx" />
                <AppTextarea v-model="testForm.message" label="Pesan (opsional)" :rows="4" placeholder="Tes koneksi WhatsApp Gateway siupk." />
            </form>
            <template #footer>
                <AppButton variant="ghost" @click="showTestModal = false">Batal</AppButton>
                <AppButton icon="send" :loading="loadingAction === `test-${testInstance?.row_id}`" @click="sendTest">Kirim Tes</AppButton>
            </template>
        </AppModal>

        </div>
    </AuthenticatedLayout>
</template>
