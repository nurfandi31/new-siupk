<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppRadioGroup from '../../../Components/AppRadioGroup.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({ member: { type: Object, default: null }, villages: { type: Array, required: true } });
const path = '/master-data/members';
const today = localIsoDate();
const defaultBirthDate = (() => {
    const date = new Date();
    date.setFullYear(date.getFullYear() - 20);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
})();
const resolvedMemberId = ref(props.member?.row_id || null);
const editing = computed(() => Boolean(resolvedMemberId.value));
const lookupLoading = ref(false);
const lookupError = ref('');
const memberFound = ref(false);
let lookupController = null;

function localIsoDate() {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function fields(member = null) {
    return {
        nik: member?.nik || '',
        name: member?.name || '',
        gender: member?.gender || '',
        birth_place: member?.birth_place || '',
        birth_date: member?.birth_date || defaultBirthDate,
        phone: member?.phone || '',
        family_card_number: member?.family_card_number || '',
        address: member?.address || '',
        village_id: member?.village_id || '',
        registered_at: member?.registered_at || today,
        status: member?.status || 'active',
        has_guarantor: Boolean(member?.guarantor),
        guarantor_nik: member?.guarantor?.nik || '',
        guarantor_name: member?.guarantor?.name || '',
        guarantor_relationship: member?.guarantor?.relationship || '',
        has_business: Boolean(member?.business),
        business_name: member?.business?.name || '',
        business_description: member?.business?.description || '',
    };
}

const form = useForm(fields(props.member));
const genderOptions = [{ value: 'L', label: 'Laki-laki', icon: 'male' }, { value: 'P', label: 'Perempuan', icon: 'female' }];
const statusOptions = [{ value: 'active', label: 'Aktif' }, { value: 'exited', label: 'Keluar' }, { value: 'deceased', label: 'Meninggal' }];

const lastLookedUpNik = ref('');

watch(() => form.nik, async (nik) => {
    if (props.member) return;

    if (!/^\d{16}$/.test(nik)) {
        lookupController?.abort();
        lookupController = null;
        lookupLoading.value = false;
        lookupError.value = '';
        return;
    }

    if (nik === lastLookedUpNik.value) return;

    lookupController?.abort();
    lookupError.value = '';

    const controller = new AbortController();
    lookupController = controller;
    lookupLoading.value = true;

    try {
        const response = await fetch(`${path}/lookup?nik=${encodeURIComponent(nik)}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Data anggota gagal diperiksa.');
        lastLookedUpNik.value = nik;
        if (payload.data) {
            resolvedMemberId.value = payload.data.row_id;
            memberFound.value = true;
            Object.assign(form, fields(payload.data));
            form.clearErrors();
        } else {
            resolvedMemberId.value = null;
            memberFound.value = false;
            Object.assign(form, { ...fields(null), nik });
        }
    } catch (error) {
        if (error.name !== 'AbortError') lookupError.value = error.message;
    } finally {
        if (lookupController === controller) {
            lookupLoading.value = false;
            lookupController = null;
        }
    }
});

onBeforeUnmount(() => lookupController?.abort());

function submit() {
    if (lookupLoading.value) return;
    editing.value ? form.put(`${path}/${resolvedMemberId.value}`) : form.post(path);
}
</script>

<template>
    <Head :title="editing ? 'Edit Anggota' : 'Tambah Anggota'" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="mb-6">
                <Link :href="path" class="text-sm font-semibold text-primary">← Kembali ke daftar anggota</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">{{ editing ? 'Edit Anggota' : 'Tambah Anggota' }}</h1>
                <p class="mt-1 text-on-surface-variant">{{ editing ? 'Perbarui identitas dan data pendukung anggota.' : 'Lengkapi identitas dan data pendukung anggota.' }}</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <div v-if="memberFound" role="status" class="flex items-center gap-3 rounded-xl border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm font-semibold text-primary">
                        <span class="material-symbols-outlined text-secondary" aria-hidden="true">person_check</span>
                        NIK sudah terdaftar. Data anggota telah dimuat dan akan diperbarui saat disimpan.
                    </div>

                    <section>
                        <h2 class="font-semibold text-primary">Identitas Anggota</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <AppInput v-model="form.nik" label="NIK" icon="badge" inputmode="numeric" maxlength="16" required :hint="lookupLoading ? 'Memeriksa data anggota…' : null" :error="form.errors.nik || lookupError" />
                            <AppInput v-model="form.name" label="Nama Lengkap" icon="person" required :error="form.errors.name" />
                            <AppRadioGroup v-model="form.gender" label="Jenis Kelamin" :options="genderOptions" required :error="form.errors.gender" />
                            <AppInput v-model="form.family_card_number" label="Nomor KK" icon="badge" inputmode="numeric" maxlength="16" :error="form.errors.family_card_number" />
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <AppInput v-model="form.birth_place" label="Tempat Lahir" icon="location_on" :error="form.errors.birth_place" />
                            <AppDatePicker v-model="form.birth_date" label="Tanggal Lahir" icon="calendar_month" placeholder="Pilih tanggal lahir" :max="today" clearable :error="form.errors.birth_date" />
                            <AppInput v-model="form.phone" label="No. HP" icon="phone" type="tel" :error="form.errors.phone" />
                            <SmartSelect v-model="form.status" label="Status" :options="statusOptions" required :error="form.errors.status" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Alamat dan Registrasi</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <AppInput v-model="form.address" label="Alamat" icon="home" required :error="form.errors.address" />
                            <SmartSelect v-model="form.village_id" label="Desa" :options="villages.map((village) => ({ value: village.row_id, label: village.name }))" placeholder="Pilih desa" required :error="form.errors.village_id" searchable />
                            <AppDatePicker v-model="form.registered_at" label="Tanggal Terdaftar" icon="event" placeholder="Pilih tanggal terdaftar" :max="today" required :error="form.errors.registered_at" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <AppSwitch v-model="form.has_guarantor" label="Penjamin" description="Opsional, satu penjamin." icon="verified_user" />
                        <div v-if="form.has_guarantor" class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <AppInput v-model="form.guarantor_nik" label="NIK Penjamin" icon="badge" inputmode="numeric" maxlength="16" required :error="form.errors.guarantor_nik" />
                            <AppInput v-model="form.guarantor_name" label="Nama Penjamin" icon="person" required :error="form.errors.guarantor_name" />
                            <AppInput v-model="form.guarantor_relationship" label="Hubungan" icon="family_restroom" required :error="form.errors.guarantor_relationship" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <AppSwitch v-model="form.has_business" label="Usaha" description="Opsional, satu usaha utama." icon="storefront" />
                        <div v-if="form.has_business" class="mt-3 grid gap-4 sm:grid-cols-2">
                            <AppInput v-model="form.business_name" label="Nama Usaha" icon="storefront" required :error="form.errors.business_name" />
                            <AppInput v-model="form.business_description" label="Deskripsi Usaha" icon="description" :error="form.errors.business_description" />
                        </div>
                    </section>

                    <div class="flex justify-end gap-3">
                        <Link :href="path"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing || lookupLoading" :disabled="lookupLoading" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
