<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import LocationMapPicker from '../../../Components/LocationMapPicker.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const form = useForm({
    name: '',
    province_code: '',
    regency_code: '',
    district_code: '',
    map_latitude: null,
    map_longitude: null,
    map_zoom: 13,
    user_name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const provinces = ref([]);
const regencies = ref([]);
const districts = ref([]);
const loading = ref(false);

const regencyCenter = reactive({
    lat: -7.5,
    lng: 109.5,
    zoom: 11,
});

async function load(url, target) {
    loading.value = true;
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Data wilayah gagal dimuat.');
        target.value = payload.data || [];
    } catch (error) {
        target.value = [];
        console.error(error);
    } finally {
        loading.value = false;
    }
}

async function loadRegencyCenter(regencyCode) {
    if (!regencyCode) return;
    try {
        const response = await fetch(`/admin/regional/regency-center/${regencyCode}`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        if (!response.ok || !payload.data) return;
        regencyCenter.lat = payload.data.lat;
        regencyCenter.lng = payload.data.lng;
        regencyCenter.zoom = payload.data.zoom || 11;
    } catch (error) {
        console.error(error);
    }
}

watch(() => form.province_code, async (value) => {
    form.regency_code = '';
    form.district_code = '';
    regencies.value = [];
    districts.value = [];
    if (value) await load(`/admin/regional/regencies/${value}`, regencies);
});

watch(() => form.regency_code, async (value) => {
    form.district_code = '';
    districts.value = [];
    if (value) {
        await load(`/admin/regional/districts/${value}`, districts);
        await loadRegencyCenter(value);
    }
});

load('/admin/regional/provinces', provinces);

function submit() {
    form.post('/admin/tenants');
}
</script>

<template>
    <Head title="Tambah Tenant" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="mb-6">
                <Link href="/admin/tenants" class="text-sm font-semibold text-primary">← Kembali ke daftar tenant</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">Tambah Tenant</h1>
                <p class="mt-1 text-on-surface-variant">Tenant dibuat bersama desa dari kecamatan terpilih dan pengguna pertama.</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <AppInput v-model="form.name" label="Nama Tenant" icon="business" required :error="form.errors.name" hint="Kode tenant dibuat otomatis dari nama." />

                    <div class="border-t border-outline-variant pt-5">
                        <h2 class="font-semibold text-primary">Wilayah Tenant</h2>
                        <div class="mt-4 grid gap-4 xl:grid-cols-3">
                            <SmartSelect v-model="form.province_code" label="Provinsi" :options="provinces.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih provinsi" required :error="form.errors.province_code" :loading="loading" searchable />
                            <SmartSelect v-model="form.regency_code" label="Kabupaten/Kota" :options="regencies.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih kabupaten/kota" required :error="form.errors.regency_code" :disabled="!form.province_code" :loading="loading" searchable />
                            <SmartSelect v-model="form.district_code" label="Kecamatan" :options="districts.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih kecamatan" required :error="form.errors.district_code" :disabled="!form.regency_code" :loading="loading" searchable />
                        </div>
                    </div>

                    <div class="border-t border-outline-variant pt-5">
                        <h2 class="font-semibold text-primary">Titik Koordinat & Peta Lokasi</h2>
                        <p class="text-xs text-on-surface-variant mb-4">Tentukan titik koordinat kantor / wilayah tenant untuk keperluan pemetaan konsolidasi.</p>
                        <LocationMapPicker
                            v-model:latitude="form.map_latitude"
                            v-model:longitude="form.map_longitude"
                            v-model:zoom="form.map_zoom"
                            :regency-center="regencyCenter"
                            :error="form.errors.map_latitude || form.errors.map_longitude"
                        />
                    </div>

                    <div class="border-t border-outline-variant pt-5">
                        <h2 class="font-semibold text-primary">Pengguna Pertama</h2>
                        <div class="mt-4 grid gap-4 xl:grid-cols-3">
                            <AppInput v-model="form.user_name" label="Nama Pengguna" icon="person" required :error="form.errors.user_name" />
                            <AppInput v-model="form.username" label="Username" icon="account_circle" required :error="form.errors.username" />
                            <AppInput v-model="form.email" label="Email" icon="mail" type="email" required :error="form.errors.email" />
                            <AppInput v-model="form.password" label="Password" icon="lock" type="password" required :error="form.errors.password" />
                            <AppInput v-model="form.password_confirmation" label="Konfirmasi Password" icon="lock_reset" type="password" required />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link href="/admin/tenants"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Daftarkan Tenant</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>