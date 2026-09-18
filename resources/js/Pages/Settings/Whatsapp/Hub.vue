<script setup>
import { useCan } from '../../../composables/useCan';
import { useConfirm } from '../../../composables/useConfirm';
import { useMoney } from '../../../composables/useMoney';
import { useToast } from '../../../composables/useToast';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
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
        toast.error(e.message);
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
        toast.error(e.message);
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
        toast.error(e.message);
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

const selectedDate = ref(props.due_date);
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

function sendBilling() {
    billingForm.due_date = props.due_date;
    billingForm.installment_row_ids = selectedIds.value;
    if (billingForm.installment_row_ids.length === 0) return;
    billingForm.post('/notifications/billing/send', { preserveScroll: true });
}

const gatewayOk = computed(() => props.billing_gateway?.configured && props.billing_gateway?.enabled);
const sourceLabel = {
    group: 'Kelompok',
    member: 'Anggota',
    beneficiary: 'Pemanfaat',
    none: '—',
};
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
                <div class="flex flex-wrap gap-2">
                    <AppBadge :tone="props.global.configured ? 'success' : 'warning'">
                        {{ props.global.configured ? 'Gateway Terkonfigurasi' : 'Gateway Belum Dikonfigurasi' }}
                    </AppBadge>
                    <AppBadge :tone="props.global.enabled ? 'success' : 'warning'">
                        {{ props.global.enabled ? 'Gateway Aktif' : 'Gateway Nonaktif' }}
                    </AppBadge>
                    <AppBadge v-if="props.billing_gateway" :tone="props.billing_gateway.state === 'open' ? 'success' : 'warning'">
                        {{ props.billing_gateway.state ? statusLabel(props.billing_gateway.state) : 'Status Tidak Diketahui' }}
                    </AppBadge>
                </div>
            </header>

            <AppTabs
                v-model="activeTab"
                :items="tabs"
                aria-label="Tab WhatsApp Hub"
                @update:model-value="go($event)"
            />

            <template v-if="activeTab === 'instances'">
                <div class="flex justify-end">
                    <AppButton type="button" icon="add" :disabled="!props.global.configured" @click="openCreate">Tambah Instance</AppButton>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <p class="text-sm text-on-surface-variant">Total Instance</p>
                    <p class="mt-1 text-2xl font-bold text-primary">{{ stats.total }}</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <p class="text-sm text-on-surface-variant">Instance Aktif</p>
                    <p class="mt-1 text-2xl font-bold text-primary">{{ stats.active }}</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <p class="text-sm text-on-surface-variant">Terhubung</p>
                    <p class="mt-1 text-2xl font-bold text-primary">{{ stats.connected }}</p>
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
                                    <div class="flex justify-end gap-2">
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
                <form class="space-y-5" @submit.prevent="submitGlobal">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Template & Aktivasi</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Template dan strategi pengiriman berlaku untuk semua notifikasi WhatsApp.</p>
                    </div>

                    <AppRadioGroup v-model="globalForm.rotation_mode" label="Strategi Rotasi Nomor" :options="rotationOptions" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppTextarea v-model="globalForm.template_billing" label="Pesan Tagihan" :rows="5" hint="Placeholder: {nama}, {angsuran_ke}, {total}, {tanggal}, {pinjaman}" />
                        <AppTextarea v-model="globalForm.template_installment" label="Pesan Angsuran" :rows="5" hint="Placeholder: {nama}, {penyetor}, {angsuran_ke}, {total}" />
                    </div>

                    <div class="flex items-center justify-between rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3">
                        <div>
                            <p class="text-sm font-bold text-primary">Aktifkan Gateway</p>
                            <p class="text-xs text-on-surface-variant">Jika nonaktif, seluruh pengiriman WhatsApp akan diblokir.</p>
                        </div>
                        <AppSwitch v-model="globalForm.is_enabled" />
                    </div>

                    <div class="flex justify-end border-t border-outline-variant pt-4">
                        <AppButton type="submit" icon="save" :loading="globalForm.processing">Simpan Pengaturan</AppButton>
                    </div>
                </form>
            </AppCard>

        <AppModal v-model="showCreateModal" :title="editingInstance ? 'Edit Instance' : 'Tambah Instance'" size="md">
            <form class="space-y-4" @submit.prevent="submitInstance">
                <AppInput v-model="form.name" label="Nama Instance" required placeholder="Contoh: Customer Service 1" :error="form.errors.name" />
                <AppInput v-model="form.phone_number" label="Nomor WhatsApp (opsional)" placeholder="08xxxxxxxxxx" :error="form.errors.phone_number" hint="Boleh diisi setelah nomor tersambung." />
                <AppInput v-model.number="form.daily_limit" label="Batas Pesan Harian" type="number" min="0" :error="form.errors.daily_limit" hint="Isi 0 untuk tanpa batas." />
                <div class="flex items-center justify-between rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3">
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
            <div class="grid gap-4 md:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jatuh tempo</p>
                    <p class="mt-2 text-lg font-bold text-primary">{{ formatDate(props.due_date) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ props.totals.count }} pinjaman · {{ props.totals.with_phone }} punya nomor</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total tagihan</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(props.totals.amount) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Dipilih</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ selectedIds.length }} · {{ money(selectedAmount) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">Instance {{ props.billing_gateway?.instance }}</p>
                </AppCard>
            </div>

            <AppCard>
                <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                    <AppDatePicker v-model="selectedDate" label="Tanggal Jatuh Tempo" />
                    <div class="flex flex-wrap items-end gap-2">
                        <AppButton variant="secondary" class="h-11" @click="selectSendable">Pilih ber-nomor</AppButton>
                        <AppButton variant="ghost" class="h-11" @click="clearSelection">Kosongkan</AppButton>
                    </div>
                </div>
            </AppCard>

            <AppCard :padded="false">
                <header class="flex flex-col gap-3 border-b border-outline-variant px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Daftar Jatuh Tempo</h2>
                        <p class="text-sm text-on-surface-variant">Hanya pinjaman aktif dengan sisa angsuran.</p>
                    </div>
                    <AppButton
                        icon="send"
                        :loading="billingForm.processing"
                        :disabled="billingForm.processing || !gatewayOk || selectedIds.length === 0"
                        @click="sendBilling"
                    >
                        Kirim ({{ selectedIds.length }})
                    </AppButton>
                </header>

                <div v-if="props.items.length === 0" class="p-6">
                    <AppEmptyState icon="event_available" title="Tidak ada jatuh tempo" description="Ubah tanggal atau pastikan ada angsuran belum lunas." />
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-on-surface-variant">
                            <tr>
                                <th class="w-12 px-4 py-3" />
                                <th class="px-4 py-3 font-semibold">Pinjaman</th>
                                <th class="px-4 py-3 font-semibold">Ke</th>
                                <th class="px-4 py-3 text-right font-semibold">Tagihan</th>
                                <th class="px-4 py-3 font-semibold">Nomor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in props.items"
                                :key="item.installment_row_id"
                                class="border-t border-outline-variant"
                                :class="!item.can_send && 'opacity-60'"
                            >
                                <td class="px-4 py-3">
                                    <AppCheckbox v-model="selected[item.installment_row_id]" :disabled="!item.can_send" />
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-primary">{{ item.borrower }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ item.loan_number || `#${item.loan_row_id}` }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-primary">{{ item.installment_number }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-primary">{{ money(item.amount) }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-mono text-sm text-primary">{{ item.phone || '—' }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ sourceLabel[item.phone_source] || item.phone_source }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
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
            <form class="space-y-4" @submit.prevent="sendTest">
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
