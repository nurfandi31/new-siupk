<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppRadioGroup from '../../../Components/AppRadioGroup.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    group: { type: Object, default: null },
    villages: { type: Array, required: true },
    businessTypes: { type: Array, required: true },
    activityTypes: { type: Array, required: true },
    groupLevels: { type: Array, required: true },
    groupFunctions: { type: Array, required: true },
});
const editing = Boolean(props.group);
const path = '/master-data/groups';
const today = localIsoDate();
const selectedMembers = ref([...(props.group?.members || [])]);
const memberOptions = ref([...(props.group?.members || [])]);
const candidateId = ref('');
const memberLoading = ref(false);
const memberSearch = ref('');
const memberSearchEmpty = ref(false);
const quickModalOpen = ref(false);
const quickSaving = ref(false);
const quickError = ref('');
const quickErrors = ref({});
const quickMember = ref({ nik: '', name: '', gender: 'L', village_id: '' });
const genderOptions = [
    { value: 'L', label: 'Laki-laki', icon: 'male' },
    { value: 'P', label: 'Perempuan', icon: 'female' },
];
let searchController = null;

const form = useForm({
    village_id: props.group?.village_id || '',
    business_type_id: props.group?.business_type_id || '',
    activity_type_id: props.group?.activity_type_id || '',
    group_level_id: props.group?.group_level_id || '',
    group_function_id: props.group?.group_function_id || '',
    name: props.group?.name || '',
    address: props.group?.address || '',
    phone: props.group?.phone || '',
    established_at: props.group?.established_at || '',
    status: props.group?.status || 'active',
    member_ids: selectedMembers.value.map((member) => member.value),
    chair_id: props.group?.chair_id || '',
    secretary_id: props.group?.secretary_id || '',
    treasurer_id: props.group?.treasurer_id || '',
});
const isActive = computed({ get: () => form.status === 'active', set: (value) => { form.status = value ? 'active' : 'inactive'; } });
const selectedOptions = computed(() => selectedMembers.value.map((member) => ({ value: member.value, label: member.label })));
const memberError = computed(() => form.errors.member_ids || Object.entries(form.errors).find(([key]) => key.startsWith('member_ids.'))?.[1]);
const villageOptions = computed(() => props.villages.map(option));
const businessOptions = computed(() => props.businessTypes.map(option));
const activityOptions = computed(() => props.activityTypes.map(option));
const levelOptions = computed(() => props.groupLevels.map(option));
const functionOptions = computed(() => props.groupFunctions.map(option));
const memberHint = computed(() => selectedMembers.value.length >= 3
    ? `${selectedMembers.value.length} anggota dipilih (memenuhi syarat).`
    : `Minimal 3 anggota. Saat ini: ${selectedMembers.value.length}.`);

function option(item) { return { value: item.row_id, label: item.name }; }
function localIsoDate() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

async function searchMembers(search = '') {
    searchController?.abort();
    const query = search.trim();
    const controller = new AbortController();
    searchController = controller;
    memberLoading.value = true;
    memberSearchEmpty.value = false;
    const exclude = selectedMembers.value.map((member) => member.value).join(',');
    try {
        const response = await fetch(`${path}/member-options?search=${encodeURIComponent(query)}&exclude=${encodeURIComponent(exclude)}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) throw new Error('Data anggota gagal dimuat.');
        const payload = await response.json();
        const selected = new Map(selectedMembers.value.map((member) => [String(member.value), member]));
        memberOptions.value = [...selected.values(), ...payload.data.filter((member) => !selected.has(String(member.value)))];
        memberSearchEmpty.value = Boolean(query) && payload.data.length === 0;
    } catch (error) {
        if (error.name !== 'AbortError') memberOptions.value = [...selectedMembers.value];
    } finally {
        if (searchController === controller) { memberLoading.value = false; searchController = null; }
    }
}

function updateMemberSearch(search) {
    memberSearch.value = search;
    memberSearchEmpty.value = false;
    searchMembers(search);
}

function openQuickMember(search) {
    const nik = /^\d{16}$/.test(search) ? search : '';
    quickMember.value = { nik, name: nik ? '' : search, gender: 'L', village_id: form.village_id || '' };
    quickErrors.value = {};
    quickError.value = '';
    quickModalOpen.value = true;
}

async function storeQuickMember() {
    if (quickSaving.value) return;
    quickSaving.value = true;
    quickErrors.value = {};
    quickError.value = '';

    try {
        const response = await window.axios.post(`${path}/members`, quickMember.value);
        const member = response.data.data;
        if (!memberOptions.value.some((item) => String(item.value) === String(member.value))) memberOptions.value.unshift(member);
        if (!selectedMembers.value.some((item) => String(item.value) === String(member.value))) selectedMembers.value.push(member);
        form.member_ids = selectedMembers.value.map((item) => item.value);
        candidateId.value = '';
        memberSearch.value = '';
        memberSearchEmpty.value = false;
        quickModalOpen.value = false;
    } catch (error) {
        if (error.response?.status === 422) {
            quickErrors.value = Object.fromEntries(Object.entries(error.response.data.errors || {}).map(([field, messages]) => [field, messages[0]]));
        } else {
            quickError.value = 'Anggota gagal didaftarkan. Silakan coba lagi.';
        }
    } finally {
        quickSaving.value = false;
    }
}

function addMember() {
    const member = memberOptions.value.find((item) => String(item.value) === String(candidateId.value));
    if (!member || selectedMembers.value.some((item) => String(item.value) === String(member.value))) return;
    selectedMembers.value.push(member);
    form.member_ids = selectedMembers.value.map((item) => item.value);
    candidateId.value = '';
}

function removeMember(memberId) {
    selectedMembers.value = selectedMembers.value.filter((member) => String(member.value) !== String(memberId));
    form.member_ids = selectedMembers.value.map((member) => member.value);
    for (const field of ['chair_id', 'secretary_id', 'treasurer_id']) {
        if (String(form[field]) === String(memberId)) form[field] = '';
    }
}

function submit() { editing ? form.put(`${path}/${props.group.row_id}`) : form.post(path); }
watch(selectedMembers, () => searchMembers(memberSearch.value), { deep: true });
onBeforeUnmount(() => searchController?.abort());
searchMembers();
</script>

<template>
    <Head :title="editing ? 'Edit Kelompok' : 'Tambah Kelompok'" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="mb-6">
                <h1 class="text-2xl font-bold text-primary">{{ editing ? 'Edit Kelompok' : 'Tambah Kelompok' }}</h1>
                <p class="mt-1 text-on-surface-variant">Kelola identitas, anggota, dan pengurus kelompok.</p>
            </header>

            <AppCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <fieldset class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div v-if="editing" class="sm:col-span-2 xl:col-span-3">
                            <AppInput :model-value="group.code" label="Kode Kelompok" icon="tag" readonly />
                        </div>
                        <AppInput v-model="form.name" label="Nama Kelompok" icon="groups" required :error="form.errors.name" />
                        <SmartSelect v-model="form.village_id" label="Desa" :options="villageOptions" searchable required :error="form.errors.village_id" />
                        <AppDatePicker v-model="form.established_at" label="Tanggal Berdiri" :max="today" clearable :error="form.errors.established_at" />
                        <SmartSelect v-model="form.business_type_id" label="Jenis Usaha" :options="businessOptions" required :error="form.errors.business_type_id" />
                        <SmartSelect v-model="form.activity_type_id" label="Jenis Kegiatan" :options="activityOptions" required :error="form.errors.activity_type_id" />
                        <SmartSelect v-model="form.group_level_id" label="Tingkatan" :options="levelOptions" required :error="form.errors.group_level_id" />
                    </fieldset>

                    <fieldset class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="sm:col-span-2 xl:col-span-4">
                            <AppTextarea v-model="form.address" label="Alamat" icon="home" placeholder="Alamat lengkap kelompok" :rows="3" :error="form.errors.address" />
                        </div>
                        <SmartSelect v-model="form.group_function_id" label="Fungsi Kelompok" :options="functionOptions" required :error="form.errors.group_function_id" />
                        <AppInput v-model="form.phone" label="No. HP" icon="phone" type="tel" :error="form.errors.phone" />
                        <div class="sm:col-span-2 xl:col-span-2">
                            <AppSwitch v-model="isActive" label="Kelompok Aktif" description="Tersedia untuk proses siupk." icon="toggle_on" field />
                        </div>
                    </fieldset>

                    <fieldset class="grid items-start gap-4 xl:grid-cols-[1fr_auto]">
                        <SmartSelect
                            v-model="candidateId"
                            label="Cari Anggota"
                            :options="memberOptions"
                            :loading="memberLoading"
                            :hint="memberHint"
                            :empty-action-label="memberSearchEmpty && memberSearch ? 'Daftarkan anggota baru' : null"
                            placeholder="Cari NIK atau nama"
                            searchable
                            @search-change="updateMemberSearch"
                            @search="searchMembers"
                            @empty-action="openQuickMember"
                        />
                        <div class="flex items-center xl:mt-7">
                            <AppButton variant="secondary" icon="person_add" :disabled="!candidateId" @click="addMember">Tambahkan</AppButton>
                        </div>
                    </fieldset>

                    <p v-if="memberError" class="-mt-2 text-sm text-error">{{ memberError }}</p>

                    <div v-if="selectedMembers.length" class="overflow-x-auto rounded-xl border border-outline-variant">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-surface-container-low">
                                <tr>
                                    <th class="px-4 py-3">NIK</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="member in selectedMembers" :key="member.value" class="border-t border-outline-variant">
                                    <td class="px-4 py-3">{{ member.nik }}</td>
                                    <td class="px-4 py-3 font-semibold">{{ member.name }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <AppButton variant="ghost" size="compact" icon="close" aria-label="Hapus anggota" @click="removeMember(member.value)">Hapus</AppButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <fieldset class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <SmartSelect v-model="form.chair_id" label="Ketua" :options="selectedOptions" :excluded-values="[form.secretary_id, form.treasurer_id].filter(Boolean)" placeholder="Pilih ketua" required searchable :error="form.errors.chair_id" />
                        <SmartSelect v-model="form.secretary_id" label="Sekretaris" :options="selectedOptions" :excluded-values="[form.chair_id, form.treasurer_id].filter(Boolean)" placeholder="Pilih sekretaris" required searchable :error="form.errors.secretary_id" />
                        <SmartSelect v-model="form.treasurer_id" label="Bendahara" :options="selectedOptions" :excluded-values="[form.chair_id, form.secretary_id].filter(Boolean)" placeholder="Pilih bendahara" required searchable :error="form.errors.treasurer_id" />
                    </fieldset>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                        <Link :href="path"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>

        <AppModal v-model="quickModalOpen" title="Daftarkan Anggota" :closeable="!quickSaving">
            <form id="quick-member-form" class="space-y-4" @submit.prevent="storeQuickMember">
                <p class="text-sm text-on-surface-variant">Lengkapi identitas ringkas. Tanggal terdaftar, alamat, dan status ditentukan otomatis.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <AppInput v-model="quickMember.nik" label="NIK" icon="badge" inputmode="numeric" maxlength="16" required autofocus :error="quickErrors.nik" />
                    <AppInput v-model="quickMember.name" label="Nama" icon="person" required :error="quickErrors.name" />
                    <AppRadioGroup v-model="quickMember.gender" label="Jenis Kelamin" :options="genderOptions" required :error="quickErrors.gender" />
                    <SmartSelect v-model="quickMember.village_id" label="Desa" :options="villageOptions" searchable required :error="quickErrors.village_id" />
                </div>
                <p v-if="quickError" class="rounded-xl bg-error/10 px-4 py-3 text-sm text-error">{{ quickError }}</p>
            </form>
            <template #footer>
                <AppButton variant="secondary" :disabled="quickSaving" @click="quickModalOpen = false">Batal</AppButton>
                <AppButton type="submit" form="quick-member-form" icon="person_add" :loading="quickSaving">Daftarkan</AppButton>
            </template>
        </AppModal>
    </AuthenticatedLayout>
</template>
