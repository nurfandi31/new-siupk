<script setup>
import { useI18n } from '../../../i18n';
const { t } = useI18n();
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppRichEditor from '../../../Components/AppRichEditor.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/TenantAdminLayout.vue';

const props = defineProps({
    page: { type: Object, default: null },
});

const editing = Boolean(props.page);
const form = useForm({
    title: props.page?.title || '',
    slug: props.page?.slug || '',
    content: props.page?.content || '',
    status: props.page?.status || 'draft',
    published_at: props.page?.published_at || '',
    meta_description: props.page?.meta_description || '',
});

const statusOptions = [
    { value: 'draft', label: t('Draf (belum tampil)') },
    { value: 'published', label: t('Terbit') },
];

const path = '/website/pages';
function submit() {
    editing ? form.put(`${path}/${props.page.row_id}`) : form.post(path);
}
</script>

<template>
    <Head :title="editing ? t('Edit Halaman') : t('Buat Halaman')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="mb-6">
                <h1 class="text-2xl font-bold text-primary">{{ editing ? t('Edit Halaman') : t('Buat Halaman') }}</h1>
                <p class="mt-1 text-on-surface-variant">{{ editing ? t('Perbarui isi halaman lalu simpan.') : t('Buat halaman statis yang akan tampil di situs publik.') }}</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <section>
                        <h2 class="font-semibold text-primary">{{ t('Konten') }}</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <AppInput v-model="form.title" :label="t('Judul Halaman')" icon="title" required :error="form.errors.title" />
                            <AppInput
                                v-model="form.slug"
                                :label="t('Slug (opsional)')"
                                icon="link"
                                hint="Kosongkan untuk dibuat otomatis dari judul. Halaman tampil di /p/{slug}."
                                :error="form.errors.slug"
                            />
                        </div>
                        <div class="mt-4">
                            <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">{{ t('Isi Halaman') }}</label>
                            <AppRichEditor v-model="form.content" :placeholder="t('Tulis isi halaman…')" />
                            <p v-if="form.errors.content" class="mt-1 text-sm text-error">{{ form.errors.content }}</p>
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">{{ t('Publikasi & SEO') }}</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <SmartSelect
                                v-model="form.status"
                                :label="t('Status')"
                                :options="statusOptions"
                                :error="form.errors.status"
                            />
                            <AppDatePicker
                                v-model="form.published_at"
                                :label="t('Tanggal Terbit')"
                                icon="event"
                                :hint="t('Kosongkan saat mempublikasikan untuk memakai waktu sekarang.')"
                                :error="form.errors.published_at"
                            />
                            <AppInput
                                v-model="form.meta_description"
                                :label="t('Meta Deskripsi')"
                                icon="manage_search"
                                class="sm:col-span-2"
                                :hint="t('Deskripsi singkat untuk mesin pencari (maks. 255 karakter).')"
                                :error="form.errors.meta_description"
                            />
                        </div>
                    </section>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-5">
                        <Link :href="path"><AppButton variant="secondary">{{ t('Batal') }}</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">{{ t('Simpan') }}</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
