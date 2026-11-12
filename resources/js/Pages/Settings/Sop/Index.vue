<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, router, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import ImageAdjuster from '../../../Components/ImageAdjuster.vue';
import AuthenticatedLayout from '../../../Layouts/TenantAdminLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';
import { useRemoteImageFile } from '../../../composables/useRemoteImageFile';
import { useToast } from '../../../composables/useToast';

const { confirm } = useConfirm();
const toast = useToast();

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

const levelPlaceholders = [t('Lancar'), t('Kurang Lancar'), t('Diragukan'), t('Macet'), t('Tidak Aktif')];

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
const logoCropOpen = ref(false);
const logoSourceFile = ref(null);

watch(
    () => props.logoUrl,
    (url) => {
        if (!url) return;
        if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
            URL.revokeObjectURL(logoPreview.value);
        }
        logoPreview.value = url;
    },
);

function onLogoChange(event) {
    setLogoFile(event.target.files?.[0] ?? null);
    event.target.value = '';
}

function onLogoDrop(event) {
    event.preventDefault();
    logoDragOver.value = false;
    setLogoFile(event.dataTransfer?.files?.[0] ?? null);
}

function setLogoFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    logoSourceFile.value = file;
    logoCropOpen.value = true;
}

function applyCroppedLogo(file) {
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
    logoForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}

const { loading: logoAdjusting, toFile: logoToFile } = useRemoteImageFile();

async function adjustCurrentLogo() {
    if (logoForm.logo) {
        logoCropOpen.value = true;
        return;
    }

    const file = await logoToFile(props.logoUrl, 'logo.png', 'image/png');
    if (!file) {
        toast.error('Gagal memuat logo saat ini. Silakan pilih file logo dari perangkat Anda.');
        return;
    }
    logoSourceFile.value = file;
    logoCropOpen.value = true;
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
        title: t('Hapus Logo Lembaga'),
        message: t('Seluruh kop dokumen dan laporan tidak akan menampilkan logo lagi. Lanjutkan?'),
        confirmLabel: t('Ya, Hapus Logo'),
        variant: 'danger',
    });
    if (!ok) return;

    router.delete('/settings/sop/logo', {
        preserveScroll: true,
        onSuccess: () => {
            if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
                URL.revokeObjectURL(logoPreview.value);
            }
            logoPreview.value = null;
            logoForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="t('SOP Lembaga')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-5 pb-12">
            <!-- Header -->
            <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-primary sm:text-3xl">{{ t('SOP Lembaga') }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ t('Logo, kolektabilitas, dan rujukan pengaturan simpanan.') }}</p>
                </div>
                <AppButton variant="secondary" size="compact" icon="arrow_back" @click="router.visit('/settings')">
                    {{ t('Kembali') }}
                </AppButton>
            </header>

            <!-- Logo lembaga -->
            <section class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
                <div class="flex items-center justify-between gap-3 border-b border-outline-variant bg-outline-variant/40 px-5 py-3">
                    <div class="flex items-center gap-2 text-sm font-bold text-primary">
                        <AppIcon name="image" tone="primary" :container-size="9" />
                        {{ t('Logo Lembaga') }}
                    </div>
                    <span class="text-xs text-on-surface-variant">{{ t('Kop dokumen & PDF') }}</span>
                </div>

                <div class="grid gap-5 p-5 sm:grid-cols-[10rem_1fr] sm:items-center sm:p-6">
                    <div class="flex justify-center">
                        <div class="relative grid size-36 place-items-center overflow-hidden rounded-2xl border-2 border-dashed border-outline-variant bg-surface-container-lowest shadow-inner">
                            <img v-if="logoPreview" :src="logoPreview" :alt="t('Logo Lembaga')" class="size-full object-contain" />
                            <div v-else class="text-center">
                                <AppIcon name="image" class="text-4xl text-on-surface-variant" />
                                <p class="mt-1 text-xs text-on-surface-variant">{{ t('Belum ada logo') }}</p>
                            </div>
                            <button
                                v-if="logoPreview"
                                type="button"
                                class="absolute bottom-1.5 right-1.5 grid size-8 place-items-center rounded-full bg-surface-container-lowest/95 text-primary shadow ring-1 ring-outline-variant transition hover:bg-surface-container disabled:opacity-60"
                                :disabled="logoAdjusting"
                                :title="t('Sesuaikan logo')"
                                :aria-label="t('Sesuaikan posisi dan ukuran logo')"
                                @click="adjustCurrentLogo"
                            >
                                <AppIcon :name="logoAdjusting ? 'progress_activity' : 'tune'" class="text-base" :class="logoAdjusting && 'animate-spin'" />
                            </button>
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
                                <span class="block text-sm font-bold text-primary">{{ t('Tarik gambar atau klik untuk pilih') }}</span>
                                <span class="mt-0.5 block text-xs text-on-surface-variant">{{ t('PNG, JPG, WebP • maks 2 MB • posisi & ukuran bisa diatur') }}</span>
                            </span>
                        </label>
                        <p v-if="logoForm.errors.logo" class="text-sm text-error">{{ logoForm.errors.logo }}</p>

                        <div v-if="logoForm.logo" class="flex items-center gap-2 text-sm text-on-surface-variant">
                            <AppIcon name="image" class="shrink-0 text-base text-outline" />
                            <span class="min-w-0 flex-1 truncate">{{ logoForm.logo.name }}</span>
                            <button
                                type="button"
                                class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold text-primary transition hover:bg-surface-container-low"
                                @click="logoCropOpen = true"
                            >
                                {{ t('Atur ulang') }}
                            </button>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2">
                            <AppButton v-if="props.logoUrl" type="button" variant="danger" size="compact" icon="delete" :disabled="logoForm.processing" @click="destroyLogo">
                                {{ t('Hapus') }}
                            </AppButton>
                            <AppButton type="submit" size="compact" icon="upload" :loading="logoForm.processing" :disabled="!logoForm.logo || logoForm.processing">
                                {{ t('Unggah Logo') }}
                            </AppButton>
                        </div>
                    </form>
                </div>
            </section>

            <ImageAdjuster
                v-model="logoCropOpen"
                :file="logoSourceFile"
                :title="t('Sesuaikan Logo Lembaga')"
                :subtitle="t('Geser logo di dalam kotak untuk mengatur posisi, lalu perbesar atau perkecil agar pas.')"
                :aspect="1"
                fit="contain"
                :output-size="512"
                output-type="image/png"
                output-name="logo.png"
                :confirm-label="t('Gunakan Logo')"
                checkerboard
                @confirm="applyCroppedLogo"
            />

            <!-- Kolektabilitas -->
            <section class="overflow-hidden rounded-2xl bg-surface-container-lowest shadow-sm ring-1 ring-outline-variant/40">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-outline-variant bg-outline-variant/40 px-5 py-3">
                    <div class="flex items-center gap-2 text-sm font-bold text-primary">
                        <AppIcon name="rule" tone="primary" :container-size="9" />
                        {{ t('Kolektabilitas') }}
                    </div>
                    <AppButton form="kolek-form" type="submit" size="compact" icon="save" :loading="kolekForm.processing" :disabled="kolekForm.processing">
                        {{ t('Simpan') }}
                    </AppButton>
                </div>

                <form id="kolek-form" class="space-y-4 p-5 sm:p-6" @submit.prevent="submitKolek">
                    <div class="flex items-start gap-2 rounded-xl border border-info/30 bg-info/10 px-3 py-2 text-xs text-info">
                        <AppIcon name="info" :container-size="6" class="shrink-0" />
                        <p>Durasi = tunggakan kurang dari (&lt;). Tingkat 4–5 boleh kosong bila hanya memakai 3 tingkat.</p>
                    </div>

                    <p v-if="Object.keys(kolekForm.errors).length" class="rounded-lg bg-error-container/40 px-3 py-2 text-xs font-semibold text-error">
                        {{ t('Periksa kembali isian kolektabilitas.') }}
                    </p>

                    <div class="overflow-x-auto">
                        <div class="min-w-[40rem] space-y-2">
                            <div class="grid grid-cols-[3rem_minmax(0,1fr)_6rem_7rem_7rem] items-center gap-2 px-1 text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">
                                <span>{{ t('Tingkat') }}</span>
                                <span>{{ t('Nama Kolek') }}</span>
                                <span>{{ t('Durasi') }}</span>
                                <span>{{ t('Satuan') }}</span>
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
                                    :label="t('Nama kolek')"
                                    hide-label
                                    size="compact"
                                    :placeholder="levelPlaceholders[idx] ?? t('Nama kolek')"
                                />
                                <AppInput
                                    v-model="row.durasi"
                                    :label="t('Durasi')"
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
                                    <option value="hari">{{ t('Hari') }}</option>
                                    <option value="bulan">{{ t('Bulan') }}</option>
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
                            <p class="text-sm font-bold text-primary">{{ t('Sistem Simpanan') }}</p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ t('Bunga, pajak, biaya, dan jenis simpanan diatur di Pengaturan &rsaquo; Sistem Simpanan.') }}</p>
                        </div>
                    </div>
                    <AppButton variant="secondary" size="compact" icon="arrow_forward" class="shrink-0" @click="router.visit('/settings?tab=savings')">
                        {{ t('Buka') }}
                    </AppButton>
                </div>
            </section>

            <p class="text-center text-xs text-on-surface-variant">{{ t('Asuransi, redaksi SPK, dan CALK menyusul.') }}</p>
        </div>
    </AuthenticatedLayout>
</template>
