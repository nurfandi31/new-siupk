<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppInput from '../../../Components/AppInput.vue';
import LocationMapPicker from '../../../Components/LocationMapPicker.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useI18n } from '../../../i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    province_code: '',
    regency_code: '',
    district_code: '',
    map_latitude: null,
    map_longitude: null,
    map_zoom: 13,
    with_simpanan: false,
});

const props = defineProps({
    seedRoles: { type: Array, default: () => [] },
    seedPassword: { type: String, default: null },
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
        if (!response.ok) throw new Error(payload.message || t('Data wilayah gagal dimuat.'));
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
    <Head :title="t('Tambah Tenant')" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary">{{ t('Tambah Tenant') }}</h1>
                <p class="mt-1 text-on-surface-variant">{{ t('Tenant dibuat bersama desa dari kecamatan terpilih. Akun login setiap role dibuat otomatis.') }}</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <AppInput v-model="form.name" :label="t('Nama Tenant')" icon="business" required :error="form.errors.name" :hint="t('Kode tenant dibuat otomatis dari nama.')" />

                    <div class="border-t border-outline-variant pt-5">
                        <h2 class="font-semibold text-primary">{{ t('Wilayah Tenant') }}</h2>
                        <div class="mt-4 grid gap-4 xl:grid-cols-3">
                            <SmartSelect v-model="form.province_code" :label="t('Provinsi')" :options="provinces.map((item) => ({ value: item.code, label: item.name }))" :placeholder="t('Pilih provinsi')" required :error="form.errors.province_code" :loading="loading" searchable />
                            <SmartSelect v-model="form.regency_code" :label="t('Kabupaten/Kota')" :options="regencies.map((item) => ({ value: item.code, label: item.name }))" :placeholder="t('Pilih kabupaten/kota')" required :error="form.errors.regency_code" :disabled="!form.province_code" :loading="loading" searchable />
                            <SmartSelect v-model="form.district_code" :label="t('Kecamatan')" :options="districts.map((item) => ({ value: item.code, label: item.name }))" :placeholder="t('Pilih kecamatan')" required :error="form.errors.district_code" :disabled="!form.regency_code" :loading="loading" searchable />
                        </div>
                    </div>

                    <div class="border-t border-outline-variant pt-5">
                        <h2 class="font-semibold text-primary">{{ t('Titik Koordinat & Peta Lokasi') }}</h2>
                        <p class="text-xs text-on-surface-variant mb-4">{{ t('Tentukan titik koordinat kantor / wilayah tenant untuk keperluan pemetaan konsolidasi.') }}</p>
                        <LocationMapPicker
                            v-model:latitude="form.map_latitude"
                            v-model:longitude="form.map_longitude"
                            v-model:zoom="form.map_zoom"
                            :regency-center="regencyCenter"
                            :error="form.errors.map_latitude || form.errors.map_longitude"
                        />
                    </div>

                    <div v-if="props.seedRoles.length" class="border-t border-outline-variant pt-5">
                        <h2 class="font-semibold text-primary">{{ t('Akun Login Otomatis') }}</h2>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            {{ t('Satu akun dibuat untuk setiap role di bawah ini. Username memakai pola') }}
                            <code class="rounded bg-surface-container-low px-1 py-0.5 font-mono text-[11px] text-primary">{kode-tenant}.{kode-role}</code>.
                            <template v-if="props.seedPassword">
                                {{ t('Password awal:') }} <code class="rounded bg-surface-container-low px-1 py-0.5 font-mono text-[11px] text-primary">{{ props.seedPassword }}</code>.
                            </template>
                            {{ t('Password dapat diubah lewat menu Users setelah tenant dibuat.') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="role in props.seedRoles"
                                :key="role.code"
                                class="rounded-full border border-outline-variant bg-surface-container-low px-3 py-1 text-xs font-semibold text-on-surface-variant"
                            >
                                {{ role.name }}
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-outline-variant pt-5">
                        <h2 class="font-semibold text-primary">{{ t('Modul Lokasi') }}</h2>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            {{ t('Modul opsional hanya membuat tabel untuk lokasi yang membutuhkannya. Modul lain tetap dapat diaktifkan kemudian dari halaman detail tenant.') }}
                        </p>
                        <label class="mt-3 flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-low p-4">
                            <AppCheckbox v-model="form.with_simpanan" />
                            <span class="min-w-0">
                                <span class="block font-semibold text-on-surface">{{ t('Sertakan modul Simpanan') }}</span>
                                <span class="block text-xs text-on-surface-variant">
                                    {{ t('Membuat tabel simp_* khusus tenant ini beserta jenis simpanan default: Pokok, Wajib, Sukarela, dan Berjangka.') }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link href="/admin/tenants"><AppButton variant="secondary">{{ t('Batal') }}</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">{{ t('Daftarkan Tenant') }}</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>