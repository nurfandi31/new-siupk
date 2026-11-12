<script setup>
import { useI18n } from '../../i18n';
const { t } = useI18n();
import { useConfirm } from '../../composables/useConfirm';
import { useRemoteImageFile } from '../../composables/useRemoteImageFile';
import { useToast } from '../../composables/useToast';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import AppTabs from '../../Components/AppTabs.vue';
import ImageAdjuster from '../../Components/ImageAdjuster.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    account: { type: Object, required: true },
    photoUrl: { type: String, default: null },
    educationOptions: { type: Array, required: true },
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);

const tabs = [
    { key: 'personal', label: t('Data Pribadi'), shortLabel: t('Pribadi'), icon: 'badge' },
    { key: 'account', label: t('Akun'), shortLabel: t('Akun'), icon: 'manage_accounts' },
    { key: 'photo', label: t('Foto'), shortLabel: t('Foto'), icon: 'account_circle' },
];

const mobileTabs = tabs.map(({ key, shortLabel, icon }) => ({ key, label: shortLabel, icon }));

const activeTab = ref(getInitialTab());

function getInitialTab() {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.some((t) => t.key === tab)) return tab;
    return flash.value?.tab ?? 'personal';
}

function go(tab) {
    router.get('/profile', { tab }, { preserveState: true, preserveScroll: true });
}

watch(() => page.url, () => {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.some((t) => t.key === tab)) activeTab.value = tab;
});

const today = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

const personalForm = useForm({
    nik: props.profile.nik ?? '',
    name: props.profile.name ?? '',
    initials: props.profile.initials ?? '',
    birth_place: props.profile.birth_place ?? '',
    birth_date: props.profile.birth_date ?? '',
    address: props.profile.address ?? '',
    phone: props.profile.phone ?? '',
    education: props.profile.education ?? '',
    appointed_at: props.profile.appointed_at ?? '',
    term_end_at: props.profile.term_end_at ?? '',
});

function submitPersonal() {
    personalForm.put('/profile', { preserveScroll: true });
}

const accountForm = useForm({
    username: props.account.username ?? '',
    password: '',
    password_confirmation: '',
});

function submitAccount() {
    accountForm.put('/profile/account', {
        preserveScroll: true,
        onSuccess: () => {
            accountForm.password = '';
            accountForm.password_confirmation = '';
        },
    });
}

const photoForm = useForm({ photo: null });
const photoPreview = ref(props.photoUrl);
const photoDragOver = ref(false);
const photoRemoving = ref(false);
const imageError = ref(false);
const photoCropOpen = ref(false);
const photoSourceFile = ref(null);

watch(() => props.photoUrl, (newUrl) => {
    if (photoPreview.value?.startsWith('blob:')) URL.revokeObjectURL(photoPreview.value);
    photoPreview.value = newUrl;
    imageError.value = false;
});

function onPhotoChange(event) {
    const file = event.target.files?.[0] ?? null;
    setPhotoFile(file);
    event.target.value = '';
}

function onPhotoDrop(event) {
    event.preventDefault();
    photoDragOver.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (file) setPhotoFile(file);
}

function setPhotoFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    photoSourceFile.value = file;
    photoCropOpen.value = true;
}

function applyCroppedPhoto(file) {
    if (photoPreview.value?.startsWith('blob:')) URL.revokeObjectURL(photoPreview.value);
    photoForm.photo = file;
    photoPreview.value = URL.createObjectURL(file);
    imageError.value = false;
}

const { loading: photoAdjusting, toFile: remoteToFile } = useRemoteImageFile();
const toast = useToast();

async function adjustCurrentPhoto() {
    if (photoForm.photo) {
        photoCropOpen.value = true;
        return;
    }
    const file = await remoteToFile(props.photoUrl, 'photo.jpg', 'image/jpeg');
    if (!file) {
        toast.error('Gagal memuat foto saat ini. Silakan pilih file dari perangkat Anda.');
        return;
    }
    photoSourceFile.value = file;
    photoCropOpen.value = true;
}

function submitPhoto() {
    photoForm.post('/profile/photo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            photoForm.reset();
            imageError.value = false;
        },
    });
}

const { confirm: confirmAction } = useConfirm();

async function destroyPhoto() {
    if (!await confirmAction({ title: t('Hapus Foto'), message: t('Hapus foto profil?') })) return;
    router.delete('/profile/photo', {
        preserveScroll: true,
        onStart: () => { photoRemoving.value = true; },
        onFinish: () => { photoRemoving.value = false; },
        onSuccess: () => {
            photoPreview.value = null;
            imageError.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('Profil')" />
    <AuthenticatedLayout>
        <div class="mx-auto w-full max-w-7xl space-y-4 sm:space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary sm:text-3xl">{{ t('Profil Pengguna') }}</h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ t('Kelola data pribadi, akun login, dan foto profil Anda.') }}
                </p>
            </header>

            <div class="grid items-start gap-4 sm:gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">
                <aside class="space-y-4 lg:sticky lg:top-20">
                    <AppCard :padded="false">
                        <div class="flex items-center gap-3 p-4">
                            <div class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-fixed ring-1 ring-outline-variant/60">
                                <img
                                    v-if="photoPreview && !imageError"
                                    :src="photoPreview"
                                    :alt="t('Foto profil')"
                                    class="size-full object-cover"
                                    @error="imageError = true"
                                />
                                <AppIcon v-else name="person" class="text-2xl text-primary" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-bold text-primary">{{ personalForm.name || t('Pengguna') }}</p>
                                <p class="truncate text-sm text-on-surface-variant">
                                    {{ accountForm.username ? `@${accountForm.username}` : 'Username belum diatur' }}
                                </p>
                            </div>
                        </div>
                        <div v-if="personalForm.phone" class="flex items-center gap-2 border-t border-outline-variant/60 px-4 py-2.5 text-sm text-on-surface-variant">
                            <AppIcon name="phone" class="shrink-0 text-base text-outline" />
                            <span class="truncate">{{ personalForm.phone }}</span>
                        </div>
                    </AppCard>

                    <div class="lg:hidden">
                        <AppTabs
                            v-model="activeTab"
                            :items="mobileTabs"
                            variant="pills-bar"
                            :aria-label="t('Bagian profil')"
                            class="[&>button]:flex-1"
                            @update:model-value="go"
                        />
                    </div>

                    <AppCard :padded="false" class="hidden p-2 lg:block">
                        <AppTabs
                            v-model="activeTab"
                            :items="tabs"
                            vertical
                            variant="pills"
                            :aria-label="t('Bagian profil')"
                            @update:model-value="go"
                        />
                    </AppCard>
                </aside>

                <div class="min-w-0 space-y-4 sm:space-y-6">
                    <!-- Data pribadi -->
                    <AppCard v-show="activeTab === 'personal'" bordered :padded="false">
                        <div class="flex items-start gap-3 border-b border-outline-variant/60 p-4 sm:p-6">
                            <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container/60 text-primary">
                                <AppIcon name="badge" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-primary sm:text-lg">{{ t('Data Pribadi') }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ t('Informasi identitas dan kontak Anda.') }}</p>
                            </div>
                        </div>

                        <form class="space-y-6 p-4 sm:p-6" @submit.prevent="submitPersonal">
                            <section class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">{{ t('Identitas') }}</h3>
                                    <span class="h-px flex-1 bg-outline-variant/70" aria-hidden="true" />
                                </div>

                                <AppInput
                                    v-model="personalForm.name"
                                    :label="t('Nama Lengkap')"
                                    icon="person"
                                    autocomplete="name"
                                    required
                                    :error="personalForm.errors.name"
                                />

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <AppInput
                                        v-model="personalForm.nik"
                                        label="NIK"
                                        icon="badge"
                                        inputmode="numeric"
                                        maxlength="16"
                                        :hint="t('16 digit sesuai KTP')"
                                        :error="personalForm.errors.nik"
                                    />
                                    <AppInput
                                        v-model="personalForm.initials"
                                        :label="t('Inisial')"
                                        icon="short_text"
                                        maxlength="10"
                                        :hint="t('Contoh: AB, JDO')"
                                        :error="personalForm.errors.initials"
                                    />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <AppInput
                                        v-model="personalForm.birth_place"
                                        :label="t('Tempat Lahir')"
                                        icon="location_on"
                                        :error="personalForm.errors.birth_place"
                                    />
                                    <AppDatePicker
                                        v-model="personalForm.birth_date"
                                        :label="t('Tanggal Lahir')"
                                        :max="today"
                                        :error="personalForm.errors.birth_date"
                                    />
                                </div>
                            </section>

                            <section class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">{{ t('Kontak') }}</h3>
                                    <span class="h-px flex-1 bg-outline-variant/70" aria-hidden="true" />
                                </div>

                                <AppInput
                                    v-model="personalForm.phone"
                                    :label="t('Nomor HP (WhatsApp)')"
                                    icon="phone"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    required
                                    :error="personalForm.errors.phone"
                                />

                                <AppTextarea
                                    v-model="personalForm.address"
                                    :label="t('Alamat Tinggal')"
                                    icon="home"
                                    rows="3"
                                    autocomplete="street-address"
                                    :error="personalForm.errors.address"
                                />
                            </section>

                            <section class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">{{ t('Pendidikan & Jabatan') }}</h3>
                                    <span class="h-px flex-1 bg-outline-variant/70" aria-hidden="true" />
                                </div>

                                <SmartSelect
                                    v-model="personalForm.education"
                                    :label="t('Pendidikan Terakhir')"
                                    :options="educationOptions"
                                    icon="school"
                                    clearable
                                    :error="personalForm.errors.education"
                                />

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <AppDatePicker
                                        v-model="personalForm.appointed_at"
                                        :label="t('Mulai Menjabat')"
                                        :error="personalForm.errors.appointed_at"
                                    />
                                    <AppDatePicker
                                        v-model="personalForm.term_end_at"
                                        :label="t('Selesai Menjabat')"
                                        :min="personalForm.appointed_at || undefined"
                                        :error="personalForm.errors.term_end_at"
                                    />
                                </div>
                            </section>

                            <div class="flex border-t border-outline-variant/60 pt-4 sm:justify-end">
                                <AppButton
                                    type="submit"
                                    icon="save"
                                    :loading="personalForm.processing"
                                    :disabled="personalForm.processing"
                                    class="w-full sm:w-auto"
                                >
                                    {{ t('Simpan Data Pribadi') }}
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>

                    <!-- Akun -->
                    <AppCard v-show="activeTab === 'account'" bordered :padded="false">
                        <div class="flex items-start gap-3 border-b border-outline-variant/60 p-4 sm:p-6">
                            <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container/60 text-primary">
                                <AppIcon name="manage_accounts" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-primary sm:text-lg">{{ t('Pengaturan Akun') }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ t('Ubah username atau password login Anda.') }}</p>
                            </div>
                        </div>

                        <form class="space-y-6 p-4 sm:p-6" @submit.prevent="submitAccount">
                            <section class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-on-surface-variant">{{ t('Kredensial') }}</h3>
                                    <span class="h-px flex-1 bg-outline-variant/70" aria-hidden="true" />
                                </div>

                                <AppInput
                                    v-model="accountForm.username"
                                    :label="t('Username')"
                                    icon="alternate_email"
                                    autocomplete="username"
                                    required
                                    :hint="t('Digunakan saat login ke sistem.')"
                                    :error="accountForm.errors.username"
                                />

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <AppInput
                                        v-model="accountForm.password"
                                        :label="t('Password Baru')"
                                        icon="lock"
                                        type="password"
                                        autocomplete="new-password"
                                        :hint="t('Minimal 8 karakter. Kosongkan jika tidak diganti.')"
                                        :error="accountForm.errors.password"
                                    />
                                    <AppInput
                                        v-model="accountForm.password_confirmation"
                                        :label="t('Konfirmasi Password')"
                                        icon="lock_reset"
                                        type="password"
                                        autocomplete="new-password"
                                        :hint="t('Ulangi password baru.')"
                                        :error="accountForm.errors.password_confirmation"
                                    />
                                </div>
                            </section>

                            <div class="flex border-t border-outline-variant/60 pt-4 sm:justify-end">
                                <AppButton
                                    type="submit"
                                    icon="save"
                                    :loading="accountForm.processing"
                                    :disabled="accountForm.processing"
                                    class="w-full sm:w-auto"
                                >
                                    {{ t('Simpan Akun') }}
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>

                    <!-- Foto -->
                    <AppCard v-show="activeTab === 'photo'" bordered :padded="false">
                        <div class="flex items-start gap-3 border-b border-outline-variant/60 p-4 sm:p-6">
                            <div class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container/60 text-primary">
                                <AppIcon name="account_circle" />
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-primary sm:text-lg">{{ t('Foto Profil') }}</h2>
                                <p class="text-sm text-on-surface-variant">{{ t('PNG, JPG, atau WebP. Maks 2 MB. Setelah memilih foto, atur posisi dan ukurannya.') }}</p>
                            </div>
                        </div>

                        <form class="p-4 sm:p-6" @submit.prevent="submitPhoto">
                            <div class="grid items-start gap-6 md:grid-cols-[13rem_minmax(0,1fr)]">
                                <div class="flex flex-col items-center gap-2">
                                    <div class="relative">
                                        <div class="grid size-40 place-items-center overflow-hidden rounded-full border border-outline-variant bg-surface-container-lowest shadow-inner">
                                            <img
                                                v-if="photoPreview && !imageError"
                                                :src="photoPreview"
                                                :alt="t('Foto profil')"
                                                class="size-full object-cover"
                                                @error="imageError = true"
                                            />
                                            <AppIcon v-else name="account_circle" class="text-6xl text-on-surface-variant" />
                                        </div>
                                        <button
                                            v-if="photoPreview && !imageError"
                                            type="button"
                                            class="absolute bottom-0 right-0 grid size-9 place-items-center rounded-full bg-surface-container-lowest/95 text-primary shadow ring-1 ring-outline-variant transition hover:bg-surface-container disabled:opacity-60"
                                            :disabled="photoAdjusting"
                                            :title="t('Sesuaikan foto')"
                                            :aria-label="t('Sesuaikan posisi dan ukuran foto profil')"
                                            @click="adjustCurrentPhoto"
                                        >
                                            <AppIcon :name="photoAdjusting ? 'progress_activity' : 'tune'" class="text-lg" :class="photoAdjusting && 'animate-spin'" />
                                        </button>
                                    </div>
                                    <p class="text-xs font-medium uppercase tracking-[0.14em] text-on-surface-variant">
                                        {{ photoPreview && !imageError ? t('Foto saat ini') : t('Belum ada foto') }}
                                    </p>
                                </div>

                                <div class="space-y-3">
                                    <label
                                        class="block cursor-pointer rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-lowest p-5 text-center transition-colors hover:border-primary hover:bg-surface-container-low sm:p-6"
                                        :class="photoDragOver ? 'border-primary bg-primary-container/20' : ''"
                                        @dragover.prevent="photoDragOver = true"
                                        @dragleave="photoDragOver = false"
                                        @drop="onPhotoDrop"
                                    >
                                        <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onPhotoChange" />
                                        <AppIcon name="upload" class="text-2xl text-on-surface-variant" />
                                        <p class="mt-2 text-sm font-bold text-primary">{{ t('Tarik foto ke sini atau klik untuk pilih') }}</p>
                                        <p class="mt-1 text-xs text-on-surface-variant">{{ t('PNG / JPG / WebP · Maks 2 MB') }}</p>
                                    </label>

                                    <div v-if="photoForm.photo" class="flex items-center gap-2 text-sm text-on-surface-variant">
                                        <AppIcon name="image" class="shrink-0 text-base text-outline" />
                                        <span class="min-w-0 flex-1 truncate">{{ photoForm.photo.name }}</span>
                                        <button
                                            type="button"
                                            class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold text-primary transition hover:bg-surface-container-low"
                                            @click="photoCropOpen = true"
                                        >
                                            {{ t('Atur ulang') }}
                                        </button>
                                    </div>
                                    <p v-if="photoForm.errors.photo" class="text-sm text-error">{{ photoForm.errors.photo }}</p>
                                </div>
                            </div>

                            <div class="mt-6 flex flex-col-reverse gap-2 border-t border-outline-variant/60 pt-4 sm:flex-row sm:justify-end">
                                <AppButton
                                    v-if="props.photoUrl || photoPreview"
                                    type="button"
                                    variant="danger"
                                    icon="delete"
                                    :loading="photoRemoving"
                                    :disabled="photoForm.processing"
                                    class="w-full sm:w-auto"
                                    @click="destroyPhoto"
                                >
                                    {{ t('Hapus Foto') }}
                                </AppButton>
                                <AppButton
                                    type="submit"
                                    icon="upload"
                                    :loading="photoForm.processing"
                                    :disabled="!photoForm.photo || photoForm.processing || photoRemoving"
                                    class="w-full sm:w-auto"
                                >
                                    {{ t('Unggah Foto') }}
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>
                </div>
            </div>
        </div>

        <ImageAdjuster
            v-model="photoCropOpen"
            :file="photoSourceFile"
            :title="t('Sesuaikan Foto Profil')"
            :subtitle="t('Geser foto untuk mengatur posisi, lalu perbesar atau perkecil agar wajah pas di lingkaran.')"
            shape="circle"
            :aspect="1"
            :output-size="640"
            output-type="image/jpeg"
            output-name="photo.jpg"
            :confirm-label="t('Gunakan Foto')"
            @confirm="applyCroppedPhoto"
        />
    </AuthenticatedLayout>
</template>
