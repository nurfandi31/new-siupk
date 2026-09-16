<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch, onMounted } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppIconButton from '../../../Components/AppIconButton.vue';
import AppInput from '../../../Components/AppInput.vue';
import LocationMapPicker from '../../../Components/LocationMapPicker.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
});

const initialProvince = props.tenant.district_code ? props.tenant.district_code.substring(0, 2) : '';
const initialRegency = props.tenant.district_code ? props.tenant.district_code.substring(0, 4) : '';

const form = useForm({
    name: props.tenant.name,
    status: props.tenant.status,
    timezone: props.tenant.timezone || 'Asia/Jakarta',
    province_code: initialProvince,
    regency_code: initialRegency,
    district_code: props.tenant.district_code || '',
    map_latitude: props.tenant.map_latitude ?? null,
    map_longitude: props.tenant.map_longitude ?? null,
    map_zoom: props.tenant.map_zoom ?? 13,
    custom_domains: Array.isArray(props.tenant.custom_domains) ? [...props.tenant.custom_domains] : [],
});

const domainInput = ref('');
const domainError = ref('');

const provinces = ref([]);
const regencies = ref([]);
const districts = ref([]);
const loading = ref(false);

const statusOptions = [
    { value: 'active', label: 'Aktif' },
    { value: 'suspended', label: 'Ditangguhkan' },
    { value: 'provisioning', label: 'Provisioning' },
    { value: 'provisioning_failed', label: 'Provisioning gagal' },
];

const regencyCenter = reactive({
    lat: -7.5,
    lng: 109.5,
    zoom: 11,
});

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

function addDomain() {
    domainError.value = '';
    const raw = domainInput.value.trim().toLowerCase();
    if (!raw) return;

    let clean = raw.replace(/^https?:\/\//, '').split('/')[0].split(':')[0].trim();
    if (!clean) return;

    if (form.custom_domains.includes(clean)) {
        domainError.value = `Domain "${clean}" sudah ada di daftar.`;
        return;
    }

    form.custom_domains.push(clean);
    domainInput.value = '';
}

function removeDomain(index) {
    form.custom_domains.splice(index, 1);
}

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

watch(() => form.province_code, async (value) => {
    regencies.value = [];
    districts.value = [];
    if (value) await load(`/admin/regional/regencies/${value}`, regencies);
});

watch(() => form.regency_code, async (value) => {
    districts.value = [];
    if (value) {
        await load(`/admin/regional/districts/${value}`, districts);
        await loadRegencyCenter(value);
    }
});

onMounted(async () => {
    await load('/admin/regional/provinces', provinces);
    if (initialProvince) {
        await load(`/admin/regional/regencies/${initialProvince}`, regencies);
    }
    if (initialRegency) {
        await load(`/admin/regional/districts/${initialRegency}`, districts);
        await loadRegencyCenter(initialRegency);
    }
});

function submit() {
    form.put(`/admin/tenants/${props.tenant.row_id}`);
}
</script>

<template>
    <Head title="Edit Tenant" />
    <AdminLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <header class="flex flex-col gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Edit Tenant</h1>
                    <p class="mt-1 text-on-surface-variant">{{ tenant.code }} · {{ tenant.name }}</p>
                </div>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <!-- Identitas -->
                    <section class="space-y-3">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Identitas</h2>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppInput v-model="form.name" label="Nama Tenant" required :error="form.errors.name" />
                            <SmartSelect v-model="form.status" label="Status" :options="statusOptions" required :error="form.errors.status" />
                        </div>
                        <AppInput v-model="form.timezone" label="Zona waktu" :error="form.errors.timezone" />
                    </section>

                    <!-- Custom Domains Section -->
                    <section class="space-y-3 border-t border-outline-variant pt-5">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Custom Domain</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">
                                Daftarkan domain atau subdomain (misal <code>bumdesma-sukamaju.id</code>). Arahkan DNS ke IP server siupk.
                            </p>
                        </div>

                        <div class="flex gap-2">
                            <AppInput
                                v-model="domainInput"
                                label="Tambah Domain"
                                placeholder="contoh: bumdesma-sukamaju.id"
                                class="flex-1"
                                :error="domainError || form.errors.custom_domains"
                                @keydown.enter.prevent="addDomain"
                            />
                            <div class="flex items-end pb-0.5">
                                <AppButton type="button" variant="primary" icon="add" @click="addDomain">Tambah</AppButton>
                            </div>
                        </div>

                        <div v-if="form.custom_domains.length > 0" class="flex flex-wrap gap-2">
                            <div
                                v-for="(dom, idx) in form.custom_domains"
                                :key="dom"
                                class="inline-flex items-center gap-2 rounded-lg border border-outline-variant bg-surface-container-low px-3 py-1.5 text-sm font-medium text-primary"
                            >
                                <AppIcon name="language" class="text-base text-outline" />
                                <span>{{ dom }}</span>
                                <AppIconButton
                                    name="close"
                                    size="sm"
                                    tone="neutral"
                                    rounded="full"
                                    aria-label="Hapus domain"
                                    class="hover:bg-error-container hover:text-error"
                                    @click="removeDomain(idx)"
                                />
                            </div>
                        </div>
                        <p v-else class="text-xs italic text-on-surface-variant">
                            Belum ada custom domain yang didaftarkan.
                        </p>

                        <div v-if="Object.keys(form.errors).some(k => k.startsWith('custom_domains.'))" class="space-y-1">
                            <template v-for="(err, key) in form.errors" :key="key">
                                <p v-if="key.startsWith('custom_domains.')" class="text-xs font-semibold text-error">
                                    {{ err }}
                                </p>
                            </template>
                        </div>
                    </section>

                    <!-- Wilayah Kecamatan Section -->
                    <section class="space-y-3 border-t border-outline-variant pt-5">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Wilayah Kecamatan</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Asosiasi tenant dengan kode wilayah resmi Indonesia.</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-3">
                            <SmartSelect v-model="form.province_code" label="Provinsi" :options="provinces.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih provinsi" :error="form.errors.province_code" :loading="loading" searchable />
                            <SmartSelect v-model="form.regency_code" label="Kabupaten/Kota" :options="regencies.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih kabupaten/kota" :error="form.errors.regency_code" :disabled="!form.province_code" :loading="loading" searchable />
                            <SmartSelect v-model="form.district_code" label="Kecamatan" :options="districts.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih kecamatan" :error="form.errors.district_code" :disabled="!form.regency_code" :loading="loading" searchable />
                        </div>
                    </section>

                    <!-- Titik Koordinat & Peta Lokasi Section -->
                    <section class="space-y-3 border-t border-outline-variant pt-5">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Titik Koordinat & Peta Lokasi</h2>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Tentukan titik koordinat kantor / wilayah tenant.</p>
                        </div>
                        <LocationMapPicker
                            v-model:latitude="form.map_latitude"
                            v-model:longitude="form.map_longitude"
                            v-model:zoom="form.map_zoom"
                            :regency-center="regencyCenter"
                            :error="form.errors.map_latitude || form.errors.map_longitude"
                        />
                    </section>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-5">
                        <Link :href="`/admin/tenants/${tenant.row_id}`"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan Perubahan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>