<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppFileUpload from '../../../Components/AppFileUpload.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AuthenticatedLayout from '../../../Layouts/TenantAdminLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    heroImageUrl: { type: String, default: null },
});

const form = useForm({
    hero_tagline: props.settings.hero_tagline ?? '',
    hero_description: props.settings.hero_description ?? '',
    about_short: props.settings.about_short ?? '',
    facebook_url: props.settings.facebook_url ?? '',
    instagram_url: props.settings.instagram_url ?? '',
    youtube_url: props.settings.youtube_url ?? '',
    contact_phone: props.settings.contact_phone ?? '',
    contact_email: props.settings.contact_email ?? '',
    contact_address: props.settings.contact_address ?? '',
    footer_note: props.settings.footer_note ?? '',
    hero_image: null,
    remove_hero_image: false,
    _method: 'PUT',
});

watch(() => form.hero_image, (file) => {
    if (file) {
        form.remove_hero_image = false;
    }
});

function submit() {
    form.post(route('website.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.hero_image = null;
            form.remove_hero_image = false;
        },
    });
}
</script>

<template>
    <Head :title="t('Pengaturan Situs')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">{{ t('Pengaturan Situs') }}</h1>
                    <p class="mt-1 text-on-surface-variant">
                        {{ t('Tampilan halaman depan, kanal sosial media, dan kontak pada situs publik desa.') }}
                    </p>
                </div>
            </header>
            <form class="space-y-6" @submit.prevent="submit">
                <AppCard padded>
                    <h2 class="text-base font-bold text-primary">{{ t('Hero Halaman Depan') }}</h2>
                    <div class="mt-4 space-y-5">
                        <AppInput
                            v-model="form.hero_tagline"
                            :label="t('Tagline')"
                            icon="campaign"
                            :placeholder="t('Masukkan tagline')"
                            :error="form.errors.hero_tagline"
                        />
                        <AppTextarea
                            v-model="form.hero_description"
                            :label="t('Deskripsi Hero')"
                            icon="notes"
                            :placeholder="t('Masukkan deskripsi')"
                            :error="form.errors.hero_description"
                        />

                        <AppFileUpload
                            v-model="form.hero_image"
                            :label="t('Gambar Hero')"
                            accept="image/png,image/jpeg,image/webp"
                            :error="form.errors.hero_image"
                        />
                        <div v-if="heroImageUrl && !form.remove_hero_image" class="flex items-center gap-4">
                            <img :src="heroImageUrl" :alt="t('Gambar hero saat ini')" class="h-24 w-40 rounded-xl border border-outline-variant object-cover">
                            <AppButton variant="secondary" size="compact" type="button" @click="form.remove_hero_image = true">
                                {{ t('Hapus gambar') }}
                            </AppButton>
                        </div>
                        <p v-if="form.remove_hero_image" class="ml-1 text-sm text-tertiary">
                            {{ t('Gambar akan dihapus saat disimpan.') }}
                            <button type="button" class="font-semibold underline" @click="form.remove_hero_image = false">{{ t('Batalkan') }}</button>
                        </p>
                    </div>
                </AppCard>

                <AppCard padded>
                    <h2 class="text-base font-bold text-primary">{{ t('Tentang Singkat') }}</h2>
                    <div class="mt-4">
                        <AppTextarea
                            v-model="form.about_short"
                            :label="t('Ringkasan Profil')"
                            icon="info"
                            :placeholder="t('Masukkan ringkasan')"
                            :error="form.errors.about_short"
                        />
                    </div>
                </AppCard>

                <AppCard padded>
                    <h2 class="text-base font-bold text-primary">{{ t('Sosial Media') }}</h2>
                    <div class="mt-4 space-y-5">
                        <AppInput
                            v-model="form.facebook_url"
                            :label="t('Facebook')"
                            icon="public"
                            type="url"
                            placeholder="https://facebook.com/"
                            :error="form.errors.facebook_url"
                        />
                        <AppInput
                            v-model="form.instagram_url"
                            :label="t('Instagram')"
                            icon="photo_camera"
                            type="url"
                            placeholder="https://instagram.com/"
                            :error="form.errors.instagram_url"
                        />
                        <AppInput
                            v-model="form.youtube_url"
                            :label="t('YouTube')"
                            icon="smart_display"
                            type="url"
                            placeholder="https://youtube.com/@"
                            :error="form.errors.youtube_url"
                        />
                    </div>
                </AppCard>

                <AppCard padded>
                    <h2 class="text-base font-bold text-primary">{{ t('Kontak & Footer') }}</h2>
                    <div class="mt-4 space-y-5">
                        <AppInput
                            v-model="form.contact_phone"
                            :label="t('Telepon Kontak')"
                            icon="call"
                            placeholder="08xx-xxxx-xxxx"
                            :error="form.errors.contact_phone"
                        />
                        <AppInput
                            v-model="form.contact_email"
                            :label="t('Email Kontak')"
                            icon="mail"
                            type="email"
                            :placeholder="t('Masukkan email')"
                            :error="form.errors.contact_email"
                        />
                        <AppTextarea
                            v-model="form.contact_address"
                            :label="t('Alamat Kontak')"
                            icon="place"
                            :placeholder="t('Masukkan alamat')"
                            :error="form.errors.contact_address"
                        />
                        <AppInput
                            v-model="form.footer_note"
                            :label="t('Catatan Footer')"
                            icon="copyright"
                            :placeholder="t('Masukkan catatan')"
                            :error="form.errors.footer_note"
                        />
                    </div>
                </AppCard>

                    <div class="flex justify-end">
                        <AppButton type="submit" icon="save" :loading="form.processing">
                            {{ t('Simpan Pengaturan') }}
                        </AppButton>
                    </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
