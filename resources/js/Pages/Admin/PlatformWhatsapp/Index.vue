<script setup>
import { useConfirm } from '../../../composables/useConfirm';
import { useToast } from '../../../composables/useToast';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppPageHeader from '../../../Components/AdminPageHeader.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    instances: { type: Array, default: () => [] },
    configured: { type: Boolean, default: false },
});

const { confirm: confirmAction } = useConfirm();
const toast = useToast();
const loadingAction = ref(null);
const editingInstance = ref(null);
const showFormModal = ref(false);
const showQrModal = ref(false);
const showTestModal = ref(false);
const qrInstance = ref(null);
const qrCode = ref(null);
const qrStatus = ref('idle');
const qrMessage = ref('');
const testInstance = ref(null);
let pollTimer = null;

const form = useForm({ name: '', phone: '' });
const testForm = useForm({ phone: '', message: '' });

const stats = computed(() => ({
    total: props.instances.length,
    active: props.instances.filter((instance) => instance.is_active).length,
    connected: props.instances.filter((instance) => isConnected(instance.status)).length,
}));

function isConnected(status) {
    return ['open', 'connected'].includes((status || '').toLowerCase());
}

function statusTone(status) {
    const value = (status || '').toLowerCase();
    if (isConnected(value)) return 'success';
    if (['connecting', 'pending'].includes(value)) return 'primary';
    if (['unconfigured', 'missing', 'disconnected', 'close', 'closed'].includes(value)) return 'warning';
    return 'neutral';
}

function statusLabel(status) {
    const value = (status || '').toLowerCase();
    if (isConnected(value)) return 'Terhubung';
    if (['connecting', 'pending'].includes(value)) return 'Menghubungkan';
    if (value === 'missing') return 'Belum Dibuat';
    if (['disconnected', 'close', 'closed'].includes(value)) return 'Terputus';
    if (value === 'unconfigured') return 'Gateway Belum Siap';
    return status || 'Tidak Diketahui';
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

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
    editingInstance.value = null;
    form.reset();
    form.clearErrors();
    showFormModal.value = true;
}

function openEdit(instance) {
    editingInstance.value = instance;
    form.name = instance.name;
    form.phone = instance.phone ?? '';
    form.clearErrors();
    showFormModal.value = true;
}

function closeForm() {
    showFormModal.value = false;
    editingInstance.value = null;
    form.reset();
    form.clearErrors();
}

function submitInstance() {
    const options = { preserveScroll: true, onSuccess: closeForm };
    if (editingInstance.value) {
        form.put(`/admin/whatsapp/${editingInstance.value.row_id}`, options);
        return;
    }
    form.post('/admin/whatsapp', options);
}

async function createSession(instance) {
    loadingAction.value = instance.row_id;
    try {
        const response = await requestJson(`/admin/whatsapp/${instance.row_id}/create-session`, { method: 'POST' });
        toast[response.success ? 'success' : 'error'](response.message || 'Berhasil');
        if (response.success) await openQr(instance, response);
    } catch (error) {
        toast.error(error.message || 'Gagal membuat QR.');
    } finally {
        loadingAction.value = null;
    }
}

async function openQr(instance, response = null) {
    qrInstance.value = instance;
    qrCode.value = response?.qr ?? null;
    qrStatus.value = response?.state ?? instance.status ?? 'connecting';
    qrMessage.value = response?.message ?? 'Menghubungkan instance platform...';
    showQrModal.value = true;
    await refreshState(instance);
    startPolling(instance);
}

async function refreshState(instance) {
    if (!qrInstance.value || qrInstance.value.row_id !== instance.row_id) return;
    try {
        const response = await requestJson(`/admin/whatsapp/${instance.row_id}/state`);
        qrCode.value = response.qr ?? null;
        qrStatus.value = response.state ?? response.status ?? 'unknown';
        qrMessage.value = response.message ?? '';
        if (isConnected(qrStatus.value)) stopPolling();
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
    qrMessage.value = '';
}

async function deleteSession(instance) {
    const confirmed = await confirmAction({
        title: 'Hapus Session Gateway',
        message: `Hapus session WhatsApp "${instance.name}" dari gateway platform?`,
        confirmLabel: 'Hapus Session',
        variant: 'danger',
        icon: 'delete',
    });
    if (!confirmed) return;

    loadingAction.value = instance.row_id;
    try {
        const response = await requestJson(`/admin/whatsapp/${instance.row_id}/delete-session`, { method: 'DELETE' });
        toast[response.success ? 'success' : 'error'](response.message || 'Session dihapus.');
    } catch (error) {
        toast.error(error.message || 'Gagal menghapus session.');
    } finally {
        loadingAction.value = null;
    }
}

async function destroyInstance(instance) {
    const confirmed = await confirmAction({
        title: 'Hapus Instance',
        message: `Session "${instance.name}" akan dihapus dari gateway dan data instance platform akan dihapus.`,
        confirmLabel: 'Hapus',
        variant: 'danger',
        icon: 'delete',
    });
    if (!confirmed) return;

    useForm({}).delete(`/admin/whatsapp/${instance.row_id}`, { preserveScroll: true });
}

async function setDefault(instance) {
    const confirmed = await confirmAction({
        title: 'Set Instance Default',
        message: `Jadikan "${instance.name}" sebagai instance OTP WhatsApp platform?`,
        confirmLabel: 'Jadikan Default',
    });
    if (!confirmed) return;

    useForm({}).post(`/admin/whatsapp/${instance.row_id}/set-default`, { preserveScroll: true });
}

function openTest(instance) {
    testInstance.value = instance;
    testForm.reset();
    testForm.clearErrors();
    showTestModal.value = true;
}

async function sendTest() {
    testForm.phone = testForm.phone.trim();
    if (!testForm.phone || !testInstance.value) return;
    loadingAction.value = `test-${testInstance.value.row_id}`;
    try {
        const response = await requestJson(`/admin/whatsapp/${testInstance.value.row_id}/test`, {
            method: 'POST',
            body: JSON.stringify({ phone: testForm.phone, message: testForm.message }),
        });
        toast[response.success ? 'success' : 'error'](response.message || 'Tes selesai.');
        if (response.success) showTestModal.value = false;
    } catch (error) {
        toast.error(error.message || 'Gagal mengirim pesan tes.');
    } finally {
        loadingAction.value = null;
    }
}

onBeforeUnmount(stopPolling);
</script>

<template>
    <Head title="WhatsApp Platform" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <AppPageHeader
                title="WhatsApp Platform"
                subtitle="Kelola nomor WhatsApp platform untuk OTP lupa password dan pengiriman system-level."
            >
                <template #actions>
                    <AppBadge :tone="props.configured ? 'success' : 'warning'">
                        {{ props.configured ? 'Gateway Terkonfigurasi' : 'Gateway Belum Dikonfigurasi' }}
                    </AppBadge>
                </template>
            </AppPageHeader>

            <div class="grid gap-4 sm:grid-cols-3">
                <AppCard>
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Instance</p>
                        <AppIcon name="devices" tone="primary" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-primary">{{ stats.total }}</p>
                </AppCard>
                <AppCard>
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Instance Aktif</p>
                        <AppIcon name="toggle_on" tone="info" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-primary">{{ stats.active }}</p>
                </AppCard>
                <AppCard>
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Terhubung</p>
                        <AppIcon name="link" tone="success" container-size="9" container-shape="pill" />
                    </div>
                    <p class="mt-3 text-2xl font-extrabold text-secondary">{{ stats.connected }}</p>
                </AppCard>
            </div>

            <AppCard bordered>
                <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Daftar Instance Platform</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Instance ini dipakai OTP lupa password sebelum fallback ke instance tenant.</p>
                    </div>
                    <AppButton icon="add" :disabled="!props.configured" @click="openCreate">Tambah Instance</AppButton>
                </div>

    <div v-if="props.instances.length" class="overflow-x-auto">
        <table class="w-full min-w-[40rem] text-left text-[11px] sm:min-w-[56rem] sm:text-sm">
                        <thead class="bg-surface-container-low">
                            <tr>
                                <th class="px-4 py-3 font-bold text-primary">Instance</th>
                                <th class="px-4 py-3 font-bold text-primary">Nomor</th>
                                <th class="px-4 py-3 font-bold text-primary">Status</th>
                                <th class="px-4 py-3 font-bold text-primary">Default</th>
                                <th class="px-4 py-3 text-right font-bold text-primary">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="instance in props.instances" :key="instance.row_id" class="border-t border-outline-variant align-top">
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-primary">{{ instance.name }}</p>
                                    <p class="mt-1 font-mono text-xs text-on-surface-variant">{{ instance.instance_name }}</p>
                                </td>
                                <td class="px-4 py-4 text-on-surface-variant">{{ instance.phone || 'Belum tersambung' }}</td>
                                <td class="px-4 py-4">
                                    <AppBadge :tone="statusTone(instance.status)">{{ statusLabel(instance.status) }}</AppBadge>
                                </td>
                                <td class="px-4 py-4">
                                    <AppBadge :tone="instance.is_default ? 'primary' : 'neutral'">
                                        {{ instance.is_default ? 'Default' : 'Cadangan' }}
                                    </AppBadge>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <AppButton size="compact" variant="secondary" icon="qr_code_scanner" :loading="loadingAction === instance.row_id" :disabled="!props.configured" @click="createSession(instance)">Buat QR</AppButton>
                                        <AppButton size="compact" variant="ghost" icon="visibility" @click="openQr(instance)">Status</AppButton>
                                        <AppButton size="compact" variant="ghost" icon="delete" @click="deleteSession(instance)">Hapus Sesi</AppButton>
                                        <AppButton size="compact" variant="ghost" icon="send" :disabled="!props.configured" @click="openTest(instance)">Uji Kirim</AppButton>
                                        <AppButton size="compact" variant="ghost" icon="star" :disabled="instance.is_default || !instance.is_active" @click="setDefault(instance)">Jadikan Default</AppButton>
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
                    title="Belum ada instance WhatsApp platform"
                    description="Tambahkan instance pertama, lalu buat QR untuk memasangkan nomor WhatsApp platform."
                />
            </AppCard>

            <AppModal v-model="showFormModal" :title="editingInstance ? 'Edit Instance Platform' : 'Tambah Instance Platform'" size="md">
                <form class="space-y-4" @submit.prevent="submitInstance">
                    <AppInput v-model="form.name" label="Nama Instance" required placeholder="Contoh: OTP Platform 1" :error="form.errors.name" />
                    <AppInput v-model="form.phone" label="Nomor WhatsApp (opsional)" placeholder="08xxxxxxxxxx" :error="form.errors.phone" hint="Boleh diisi setelah nomor tersambung." />
                </form>
                <template #footer>
                    <AppButton variant="ghost" @click="closeForm">Batal</AppButton>
                    <AppButton :loading="form.processing" @click="submitInstance">{{ editingInstance ? 'Simpan' : 'Tambah' }}</AppButton>
                </template>
            </AppModal>

            <AppModal v-model="showQrModal" :title="qrInstance ? `Status ${qrInstance.name}` : 'Status Instance'" size="sm">
                <div class="space-y-4 text-center">
                    <AppBadge :tone="statusTone(qrStatus)">{{ statusLabel(qrStatus) }}</AppBadge>
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

            <AppModal v-model="showTestModal" title="Uji Kirim WhatsApp Platform" size="sm">
                <form class="space-y-4" @submit.prevent="sendTest">
                    <AppInput v-model="testForm.phone" label="Nomor Tujuan" required placeholder="08xxxxxxxxxx" />
                    <AppTextarea v-model="testForm.message" label="Pesan (opsional)" :rows="4" placeholder="Tes koneksi WhatsApp platform siupk." />
                </form>
                <template #footer>
                    <AppButton variant="ghost" @click="showTestModal = false">Batal</AppButton>
                    <AppButton icon="send" :loading="loadingAction === `test-${testInstance?.row_id}`" @click="sendTest">Kirim Tes</AppButton>
                </template>
            </AppModal>
        </div>
    </AdminLayout>
</template>
