<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({ village: { type: Object, required: true }, villageNamings: { type: Array, required: true } });
const path = '/master-data/villages';
const form = useForm({
    address: props.village.address || '',
    phone: props.village.phone || '',
    village_head_name: props.village.village_head_name || '',
    village_head_phone: props.village.village_head_phone || '',
    village_head_nip: props.village.village_head_nip || '',
    village_secretary_name: props.village.village_secretary_name || '',
    village_secretary_phone: props.village.village_secretary_phone || '',
    village_council_name: props.village.village_council_name || '',
    installment_schedule: props.village.installment_schedule ?? 'follow_disbursement',
    village_naming_id: props.village.village_naming_id || '',
});
const installmentOptions = [
    { value: 'follow_disbursement', label: 'Mengikuti tanggal cair' },
    ...Array.from({ length: 31 }, (_, index) => ({
        value: String(index + 1),
        label: `Tanggal ${index + 1}`,
    })),
];
const currentInstallment = computed({
    get: () => {
        const value = String(form.installment_schedule || '');
        if (!/^\d+$/.test(value)) return value;
        const number = Number(value);
        return number >= 1 && number <= 31 ? String(number) : value;
    },
    set: (value) => { form.installment_schedule = value; },
});

function submit() {
    form.put(`${path}/${props.village.row_id}`);
}
</script>

<template>
    <Head title="Edit Desa" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="mb-6">
                <Link :href="path" class="text-sm font-semibold text-primary">← Kembali ke daftar desa</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">Edit Desa</h1>
                <p class="mt-1 text-on-surface-variant">Identitas wilayah berasal dari master wilayah dan tidak dapat diubah.</p>
            </header>
            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput :model-value="village.code" label="Kode Desa" icon="tag" readonly />
                        <AppInput :model-value="village.name" label="Nama Desa" icon="location_city" readonly />
                    </div>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Kontak Desa</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <AppInput v-model="form.address" label="Alamat" icon="home" :error="form.errors.address" />
                            <AppInput v-model="form.phone" label="No. HP" icon="phone" type="tel" :error="form.errors.phone" />
                            <SmartSelect v-model="form.village_naming_id" label="Sebutan Desa" :options="villageNamings.map((naming) => ({ value: naming.row_id, label: `${naming.village_name} / ${naming.village_head_name}` }))" placeholder="Pilih sebutan desa" required :error="form.errors.village_naming_id" searchable />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Kepala Desa/Lurah</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <AppInput v-model="form.village_head_name" label="Nama Kades/Lurah" icon="person" :error="form.errors.village_head_name" />
                            <AppInput v-model="form.village_head_phone" label="HP Kades/Lurah" icon="phone" type="tel" :error="form.errors.village_head_phone" />
                            <AppInput v-model="form.village_head_nip" label="NIP Kades/Lurah" icon="badge" :error="form.errors.village_head_nip" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Sekretaris dan Lembaga Desa</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <AppInput v-model="form.village_secretary_name" label="Nama Sekdes" icon="person" :error="form.errors.village_secretary_name" />
                            <AppInput v-model="form.village_secretary_phone" label="HP Sekdes" icon="phone" type="tel" :error="form.errors.village_secretary_phone" />
                            <AppInput v-model="form.village_council_name" label="Nama Ketua/LPMD/BPD" icon="groups" :error="form.errors.village_council_name" />
                            <SmartSelect v-model="currentInstallment" label="Jadwal Angsuran Desa" :options="installmentOptions" placeholder="Pilih jadwal angsuran" required :error="form.errors.installment_schedule" />
                        </div>
                    </section>

                    <div class="flex justify-end gap-3">
                        <Link :href="path"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
