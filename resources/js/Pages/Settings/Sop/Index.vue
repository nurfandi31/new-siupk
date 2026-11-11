<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AuthenticatedLayout from '../../../Layouts/TenantAdminLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    kolek: {
        type: Object,
        required: true,
        // { levels: [...summary], rows: [...5 rows associative] }
    },
    logoUrl: {
        type: String,
        default: null,
    },
    savingsEnabled: {
        type: Boolean,
        default: false,
    },
});

const levelPlaceholders = ['Lancar', 'Kurang Lancar', 'Diragukan', 'Macet', 'Tidak Aktif'];

// Pakai useForm untuk handle submit + error display
const kolekForm = useForm({
    rows: props.kolek.rows.map((r) => ({ ...r })),
});

function submitKolek() {
    const payload = {};
    kolekForm.rows.forEach((row, idx) => {
        const i = idx + 1;
        payload[`nama_kolek${i}`] = row.nama ?? '';
        payload[`pros_kolek${i}`] = row.prosentase ?? '';
        payload[`durasi${i}`] = row.durasi ?? '';
        payload[`satuan${i}`] = row.satuan || 'bulan';
    });

    kolekForm
        .transform(() => payload)
        .put('/settings/sop/kolek', {
            preserveScroll: true,
        });
}

// === Logo lembaga (dipakai seluruh kop dokumen & pelaporan) ===
const logoForm = useForm({ logo: null });
const logoPreview = ref(props.logoUrl);
const logoDragOver = ref(false);

watch(
    () => props.logoUrl,
    (url) => {
        if (url) logoPreview.value = url;
    },
);

function onLogoChange(event) {
    setLogoFile(event.target.files?.[0] ?? null);
}

function onLogoDrop(event) {
    event.preventDefault();
    logoDragOver.value = false;
    setLogoFile(event.dataTransfer?.files?.[0] ?? null);
}

function setLogoFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

onBeforeUnmount(() => {
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
});

function submitLogo() {
    logoForm.post('/settings/sop/logo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            logoForm.reset();
        },
    });
}

async function destroyLogo() {
    const ok = await confirm({
        title: 'Hapus Logo Lembaga',
        message: 'Seluruh kop dokumen dan laporan tidak akan menampilkan logo lagi. Lanjutkan?',
        confirmLabel: 'Ya, Hapus Logo',
        variant: 'danger',
    });
    if (!ok) return;

    router.delete('/settings/sop/logo', {
        preserveScroll: true,
        onSuccess: () => {
            logoPreview.value = null;
        },
    });
}
</script>

<template>
    <Head title="SOP Lembaga" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-5 pb-12">
            <!-- Header -->
            <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-primary sm:text-3xl">SOP Lembaga</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">Logo, kolektabilitas, dan rujukan pengaturan simpanan.</p>
                </div>
                <AppButton variant="secondary" size="compact" icon="arrow_back" @click="router.visit('/settings')">
                    Kembali
                </AppButton>
            </header>

            <!-- Logo lembaga -->
            <section class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
                <div class="flex items-center justify-between gap-3 border-b border-outline-variant bg-outline-variant/40 px-5 py-3">
                    <div class="flex items-center gap-2 text-sm font-bold text-primary">
                        <AppIcon name="image" tone="primary" :container-size="9" />
                        Logo Lembaga
                    </div>
                    <span class="text-xs text-on-surface-variant">Kop dokumen &amp; PDF</span>
                </div>

                <div class="grid gap-5 p-5 sm:grid-cols-[10rem_1fr] sm:items-center sm:p-6">
                    <div class="flex justify-center">
                        <div class="grid size-36 place-items-center overflow-hidden rounded-2xl border-2 border-dashed border-outline-variant bg-surface-container-lowest shadow-inner">
                            <img v-if="logoPreview" :src="logoPreview" alt="Logo Lembaga" class="size-full object-contain" />
                            <div v-else class="text-center">
                                <AppIcon name="image" class="text-4xl text-on-surface-variant" />
                                <p class="mt-1 text-xs text-on-surface-variant">Belum ada logo</p>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-3" @submit.prevent="submitLogo">
                        <label
                            class="flex cursor-pointer flex-col items-center gap-3 rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-lowest p-4 text-center transition-all hover:border-primary hover:bg-primary-container/10 sm:flex-row sm:text-left"
                            :class="logoDragOver ? 'border-primary bg-primary-container/20' : ''"
                            @dragover.prevent="logoDragOver = true"
                            @dragleave="logoDragOver = false"
                            @drop="onLogoDrop"
                        >
                            <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onLogoChange" />
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-primary-container/30 text-primary">
                                <AppIcon name="cloud_upload" class="text-xl" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-primary">Tarik gambar atau klik untuk pilih</span>
                                <span class="mt-0.5 block text-xs text-on-surface-variant">PNG, JPG, WebP &bull; maks 2 MB</span>
                            </span>
                        </label>
                        <p v-if="logoForm.errors.logo" class="text-sm text-error">{{ logoForm.errors.logo }}</p>

                        <div class="flex flex-wrap justify-end gap-2">
                            <AppButton v-if="logoPreview" type="button" variant="danger" size="compact" icon="delete" :disabled="logoForm.processing" @click="destroyLogo">
                                Hapus
                            </AppButton>
                            <AppButton type="submit" size="compact" icon="upload" :loading="logoForm.processing" :disabled="!logoForm.logo || logoForm.processing">
                                Unggah Logo
                            </AppButton>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Kolektabilitas -->
            <section class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant bg-outline-variant/40 px-5 py-3">
                    <div class="flex items-center gap-2 text-sm font-bold text-primary">
                        <AppIcon name="rule" tone="primary" :container-size="9" />
                        Kolektabilitas
                    </div>
                    <AppButton form="kolek-form" type="submit" size="compact" icon="save" :loading="kolekForm.processing" :disabled="kolekForm.processing">
                        Simpan
                    </AppButton>
                </div>

                <form id="kolek-form" class="space-y-4 p-5 sm:p-6" @submit.prevent="submitKolek">
                    <div class="flex items-start gap-2 rounded-xl border border-info/30 bg-info/10 px-3 py-2 text-xs text-info">
                        <AppIcon name="info" :container-size="6" class="shrink-0" />
                        <p>Durasi = tunggakan kurang dari (&lt;). Tingkat 4–5 boleh kosong bila hanya memakai 3 tingkat.</p>
                    </div>

                    <p v-if="Object.keys(kolekForm.errors).length" class="rounded-lg bg-error-container/40 px-3 py-2 text-xs font-semibold text-error">
                        Periksa kembali isian kolektabilitas.
                    </p>

                    <div class="overflow-x-auto">
                        <div class="min-w-[40rem] space-y-2">
                            <div class="grid grid-cols-[3rem_minmax(0,1fr)_6rem_7rem_7rem] items-center gap-2 px-1 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">
                                <span>Tingkat</span>
                                <span>Nama Kolek</span>
                                <span>Durasi</span>
                                <span>Satuan</span>
                                <span>CKPN (%)</span>
                            </div>

                            <div
                                v-for="(row, idx) in kolekForm.rows"
                                :key="idx"
                                class="grid grid-cols-[3rem_minmax(0,1fr)_6rem_7rem_7rem] items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-low/40 p-2"
                            >
                                <span class="grid size-8 place-items-center rounded-lg bg-primary-container/40 text-sm font-bold text-primary">{{ idx + 1 }}</span>
                                <AppInput
                                    v-model="row.nama"
                                    label="Nama kolek"
                                    hide-label
                                    size="compact"
                                    :placeholder="levelPlaceholders[idx] ?? 'Nama kolek'"
                                />
                                <AppInput
                                    v-model="row.durasi"
                                    label="Durasi"
                                    hide-label
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    size="compact"
                                    placeholder="3"
                                />
                                <select
                                    v-model="row.satuan"
                                    class="h-9 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-2 text-sm text-primary focus:border-primary-container focus:outline-none focus:ring-2 focus:ring-primary-container/10"
                                >
                                    <option value="hari">Hari</option>
                                    <option value="bulan">Bulan</option>
                                </select>
                                <AppInput
                                    v-model="row.prosentase"
                                    label="CKPN"
                                    hide-label
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    size="compact"
                                    placeholder="1"
                                />
                            </div>
                        </div>
                    </div>
                </form>
            </section>

            <!-- Pengaturan simpanan terpusat di menu Pengaturan -->
            <section v-if="savingsEnabled" class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div class="flex items-start gap-3">
                        <AppIcon name="savings" tone="primary" :container-size="9" />
                        <div>
                            <p class="text-sm font-bold text-primary">Sistem Simpanan</p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Bunga, pajak, biaya, dan jenis simpanan diatur di Pengaturan &rsaquo; Sistem Simpanan.</p>
                        </div>
                    </div>
                    <AppButton variant="secondary" size="compact" icon="arrow_forward" class="shrink-0" @click="router.visit('/settings?tab=savings')">
                        Buka
                    </AppButton>
                </div>
            </section>

            <p class="text-center text-xs text-on-surface-variant">Asuransi, redaksi SPK, dan CALK menyusul.</p>
        </div>
    </AuthenticatedLayout>
</template>
