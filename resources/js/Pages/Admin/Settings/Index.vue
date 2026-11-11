<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminPageHeader from '../../../Components/AdminPageHeader.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';

const props = defineProps({
    settings: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 30 },
    summary: { type: Object, required: true },
});

const { confirm } = useConfirm();

const editingKey = ref(null);
const createOpen = ref(false);

const typeOptions = [
    { value: 'string', label: t('String') },
    { value: 'int', label: t('Angka (int)') },
    { value: 'float', label: t('Desimal (float)') },
    { value: 'bool', label: t('Boolean') },
    { value: 'json', label: 'JSON' },
];

const editForm = useForm({ key: '', value: '', value_type: 'string' });
const createForm = useForm({ key: '', value: '', value_type: 'string' });

const columns = [
    { key: 'key', label: t('Key') },
    { key: 'value_type', label: t('Tipe') },
    { key: 'value', label: t('Nilai') },
    { key: 'updated_at', label: t('Diperbarui') },
];

function startEdit(row) {
    editingKey.value = row.key;
    editForm.key = row.key;
    editForm.value = '';
    editForm.value_type = row.value_type;
}

function cancelEdit() {
    editingKey.value = null;
    editForm.reset();
}

function saveEdit() {
    editForm.post('/admin/settings', {
        preserveScroll: true,
        onSuccess: () => cancelEdit(),
    });
}

function saveCreate() {
    createForm.post('/admin/settings', {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createOpen.value = false;
        },
    });
}

async function removeSetting(key) {
    const ok = await confirm({
        title: t('Hapus Setting?'),
        message: `Setting [${key}] akan dihapus permanen dari database platform. Fitur yang bergantung padanya akan kembali ke nilai default.`,
        confirmLabel: t('Hapus'),
        variant: 'danger',
    });
    if (!ok) return;

    router.delete('/admin/settings', {
        data: { key },
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('Pengaturan Platform')" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6">
            <AdminPageHeader
                :title="t('Pengaturan Platform')"
                :subtitle="t('Key-value store tingkat instalasi yang berlaku untuk semua tenant — kredensial payment gateway, template WhatsApp, dan konfigurasi integrasi lainnya.')"
            >
                <template #actions>
                    <AppButton icon="add" @click="createOpen = !createOpen">{{ createOpen ? t('Tutup') : t('Setting Baru') }}</AppButton>
                </template>
            </AdminPageHeader>

            <!-- KPI -->
            <div class="grid gap-4 sm:grid-cols-2">
                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Total Setting') }}</p>
                        <AppIcon name="tune" tone="primary" container-size="8" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold tabular-nums text-primary">{{ summary.total }}</p>
                </AppCard>
                <AppCard>
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">{{ t('Nilai Sensitif (terenkripsi)') }}</p>
                        <AppIcon name="encrypted" :tone="summary.sensitive > 0 ? 'success' : 'neutral'" container-size="8" container-shape="pill" />
                    </div>
                    <p class="mt-2 text-2xl font-extrabold tabular-nums text-primary">{{ summary.sensitive }}</p>
                    <p class="mt-1.5 text-xs text-on-surface-variant">{{ t('Disimpan terenkripsi & tidak pernah tampil di layar') }}</p>
                </AppCard>
            </div>

            <!-- Form setting baru -->
            <AppCard v-if="createOpen">
                <form class="grid gap-4 sm:grid-cols-[1fr_10rem_2fr_auto] sm:items-end" @submit.prevent="saveCreate">
                    <AppInput v-model="createForm.key" :label="t('Key')" :placeholder="t('Masukkan key')" required />
                    <SmartSelect
                        v-model="createForm.value_type"
                        :label="t('Tipe')"
                        :options="typeOptions"
                        value-key="value"
                    />
                    <AppInput
                        v-model="createForm.value"
                        :label="t('Nilai')"
                        :placeholder="t('Masukkan nilai')"
                        :type="/(secret|api_key|private_key|token|password)/i.test(createForm.key) ? 'password' : 'text'"
                    />
                    <AppButton type="submit" icon="save" :loading="createForm.processing">{{ t('Simpan') }}</AppButton>
                </form>
                <p class="mt-3 text-xs text-on-surface-variant">
                    Key yang mengandung <code>secret / api_key / private_key / token / password</code> otomatis disimpan terenkripsi dan nilainya tidak akan pernah dikirim balik ke browser.
                </p>
            </AppCard>

            <AppCard :padded="false">
                <SmartDataTable
                    :rows="settings.data"
                    :columns="columns"
                    :pagination="settings"
                    url="/admin/settings"
                    :search="search"
                    :per-page="perPage"
                    search-label="Cari key"
                    search-placeholder="…"
                    empty-title="Belum ada setting"
                    empty-description="Setting akan muncul otomatis saat fitur pertama kali menyimpan konfigurasi."
                >
                    <template #cell-key="{ row }">
                        <span class="font-mono text-sm font-semibold text-primary">{{ row.key }}</span>
                        <AppBadge v-if="row.is_sensitive" tone="warning-soft" class="ml-1.5">terenkripsi</AppBadge>
                    </template>
                    <template #cell-value_type="{ row }">
                        <AppBadge tone="info-soft" class="whitespace-nowrap">{{ row.value_type }}</AppBadge>
                    </template>
                    <template #cell-value="{ row }">
                        <span v-if="editingKey !== row.key" class="break-all font-mono text-xs text-on-surface">{{ row.has_value ? row.value || t('(kosong)') : t('(kosong)') }}</span>
                        <form v-else class="flex flex-wrap items-end gap-2" @submit.prevent="saveEdit">
                            <AppInput
                                v-model="editForm.value"
                                :label="`Nilai baru (${editForm.value_type})`"
                                hide-label
                                class="min-w-64 flex-1"
                                :type="row.is_sensitive ? 'password' : 'text'"
                                :placeholder="row.is_sensitive ? 'Ganti nilai' : 'Nilai baru'"
                            />
                            <AppButton type="submit" variant="success" size="compact" icon="check" :loading="editForm.processing">{{ t('Simpan') }}</AppButton>
                            <AppButton variant="ghost" size="compact" @click="cancelEdit">{{ t('Batal') }}</AppButton>
                        </form>
                    </template>
                    <template #cell-updated_at="{ row }">
                        <span v-if="row.updated_at" class="whitespace-nowrap tabular-nums text-on-surface">{{ row.updated_at }}</span>
                        <span v-else class="text-outline">—</span>
                    </template>
                    <template #actions="{ row }">
                        <div class="flex items-center justify-end gap-1.5">
                            <AppButton
                                v-if="editingKey !== row.key"
                                variant="secondary"
                                size="compact"
                                icon="edit"
                                @click="startEdit(row)"
                            >
                                {{ t('Ubah') }}
                            </AppButton>
                            <AppButton
                                variant="ghost"
                                size="compact"
                                icon="delete"
                                class="text-error"
                                @click="removeSetting(row.key)"
                            >
                                {{ t('Hapus') }}
                            </AppButton>
                        </div>
                    </template>
                </SmartDataTable>
            </AppCard>
        </div>
    </AdminLayout>
</template>
