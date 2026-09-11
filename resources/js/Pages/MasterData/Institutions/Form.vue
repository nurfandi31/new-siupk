<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    institution: { type: Object, default: null },
    villages: { type: Array, required: true },
});

const editing = Boolean(props.institution);
const form = useForm({
    parent_row_id: props.institution?.parent_row_id || '',
    name: props.institution?.name || '',
    address: props.institution?.address || '',
    phone: props.institution?.phone || '628',
    institution_identity_number: props.institution?.institution_identity_number || '',
    leader_name: props.institution?.leader_name || '',
    responsible_name: props.institution?.responsible_name || '',
    is_active: props.institution?.is_active ?? true,
});

const path = '/master-data/institutions';
function submit() {
    editing ? form.put(`${path}/${props.institution.row_id}`) : form.post(path);
}
</script>

<template>
    <Head :title="editing ? 'Edit Lembaga' : 'Tambah Lembaga'" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="mb-6">
                <Link :href="path" class="text-sm font-semibold text-primary">← Kembali ke daftar lembaga</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">{{ editing ? 'Edit Lembaga' : 'Tambah Lembaga' }}</h1>
                <p class="mt-1 text-on-surface-variant">{{ editing ? 'Perbarui identitas dan penanggungjawab lembaga.' : 'Lengkapi identitas dan penanggungjawab lembaga.' }}</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <section>
                        <h2 class="font-semibold text-primary">Identitas Lembaga</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2" :class="editing ? 'xl:grid-cols-3' : 'xl:grid-cols-2'">
                            <AppInput v-if="editing" :model-value="institution.code" label="Kode Lembaga" icon="tag" readonly />
                            <AppInput v-model="form.name" label="Nama Lembaga" icon="business" required :error="form.errors.name" />
                            <SmartSelect
                                v-model="form.parent_row_id"
                                label="Desa"
                                :options="villages.map((village) => ({ value: village.row_id, label: village.name }))"
                                required
                                :error="form.errors.parent_row_id"
                                searchable
                            />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Kontak Lembaga</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <AppInput v-model="form.address" label="Alamat Lembaga" icon="home" :error="form.errors.address" />
                            <AppInput v-model="form.phone" label="No. HP" icon="phone" type="tel" inputmode="numeric" pattern="[0-9]*" :error="form.errors.phone" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Penanggung Jawab</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <AppInput v-model="form.leader_name" label="Nama Pimpinan" icon="person" required :error="form.errors.leader_name" />
                            <AppInput v-model="form.responsible_name" label="Nama Penanggungjawab" icon="person_check" required :error="form.errors.responsible_name" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Identitas Tambahan</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <AppInput v-model="form.institution_identity_number" label="Nomor Identitas Lembaga" icon="badge" required :error="form.errors.institution_identity_number" />
                            <AppSwitch v-model="form.is_active" label="Lembaga aktif" description="Lembaga tersedia untuk perguliran." icon="toggle_on" />
                        </div>
                    </section>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-5">
                        <Link :href="path"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
