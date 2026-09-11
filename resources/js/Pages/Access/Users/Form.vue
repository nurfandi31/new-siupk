<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    user: { type: Object, default: null },
    roleOptions: { type: Array, default: () => [] },
    villageOptions: { type: Array, default: () => [] },
});

const editing = !!props.user;

const form = useForm({
    name: props.user?.name || '',
    username: props.user?.username || '',
    email: props.user?.email || '',
    phone: props.user?.phone || '',
    password: '',
    password_confirmation: '',
    status: props.user?.status || 'active',
    appointed_at: props.user?.appointed_at || '',
    term_end_at: props.user?.term_end_at || '',
    role: props.user?.role || '',
    member_row_id: props.user?.member_row_id || '',
    is_village_user: Boolean(props.user?.is_village_user),
    village_row_id: props.user?.village_row_id || '',
});

const statusOptions = [
    { value: 'active', label: 'Aktif (Dapat Login)' },
    { value: 'suspended', label: 'Ditangguhkan (Akses Ditutup Sementara)' },
    { value: 'inactive', label: 'Nonaktif' },
];

const showVillageSelect = computed(() => form.is_village_user || form.role === 'village_operator');
const showMemberSelect = computed(() => form.role === 'anggota');
const memberOptions = ref(props.user?.member_row_id ? [{
    value: props.user.member_row_id,
    label: props.user.member_name ? `${props.user.member_name} · ${props.user.member_row_id}` : String(props.user.member_row_id),
}] : []);
const memberLoading = ref(false);
let memberSearchController;

async function searchMembers(search = '') {
    memberSearchController?.abort();
    const controller = new AbortController();
    memberSearchController = controller;
    memberLoading.value = true;
    try {
        const response = await fetch(`/master-data/groups/member-options?search=${encodeURIComponent(search.trim())}`, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error('Data anggota gagal dimuat.');
        const payload = await response.json();
        const selected = memberOptions.value.find((option) => String(option.value) === String(form.member_row_id));
        memberOptions.value = selected ? [selected, ...payload.data.filter((option) => String(option.value) !== String(selected.value))] : payload.data;
    } catch (error) {
        if (error.name !== 'AbortError') memberOptions.value = [];
    } finally {
        if (!controller.signal.aborted) memberLoading.value = false;
    }
}

onMounted(() => {
    if (showMemberSelect.value) searchMembers();
});

function submit() {
    if (editing) {
        form.put(`/access/users/${props.user.row_id}`);
    } else {
        form.post('/access/users');
    }
}
</script>

<template>
    <Head :title="editing ? 'Edit Pengguna' : 'Tambah Pengguna'" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <header>
                <Link href="/access/users" class="text-sm font-semibold text-primary hover:underline">
                    ← Kembali ke Manajemen Pengguna
                </Link>
                <h1 class="mt-2 text-2xl font-bold text-primary sm:text-3xl">
                    {{ editing ? `Edit Pengguna: ${user.name}` : 'Tambah Pengguna Baru' }}
                </h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    Atur identitas akun, hak akses peran (role), dan status pengguna.
                </p>
            </header>

            <form class="space-y-6" @submit.prevent="submit">
                <AppCard title="Informasi Akun">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput v-model="form.name" label="Nama Lengkap" placeholder="Contoh: Budi Santoso" required :error="form.errors.name" />
                        <AppInput v-model="form.username" label="Username Login" placeholder="Contoh: budi_s" required :error="form.errors.username" />
                        <div class="sm:col-span-2">
                            <AppInput v-model="form.email" label="Alamat Email (Opsional)" placeholder="Contoh: budi@contoh.id" type="email" :error="form.errors.email" />
                        </div>
                        <div class="sm:col-span-2">
                            <AppInput v-model="form.phone" label="Nomor HP (WhatsApp)" icon="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="08xxxxxxxxxx" required :error="form.errors.phone" />
                        </div>

                        <div v-if="showMemberSelect" class="border-t border-outline-variant pt-4">
                            <SmartSelect
                                v-model="form.member_row_id"
                                label="Anggota Terhubung"
                                :options="memberOptions"
                                searchable
                                required
                                clearable
                                placeholder="Cari nama atau nomor anggota..."
                                :loading="memberLoading"
                                :error="form.errors.member_row_id"
                                @search="searchMembers"
                            />
                        </div>
                    </div>
                </AppCard>

                <AppCard title="Keamanan & Password">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput
                            v-model="form.password"
                            :label="editing ? 'Password Baru (Kosongkan jika tidak diubah)' : 'Password'"
                            type="password"
                            :required="!editing"
                            :error="form.errors.password"
                        />
                        <AppInput
                            v-model="form.password_confirmation"
                            label="Konfirmasi Password"
                            type="password"
                            :required="!editing || Boolean(form.password)"
                        />
                    </div>
                </AppCard>

                <AppCard title="Peran & Status Akses">
                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <SmartSelect v-model="form.role" label="Peran (Role)" :options="roleOptions" :error="form.errors.role" />
                            <SmartSelect v-model="form.status" label="Status Akun" :options="statusOptions" required :error="form.errors.status" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 border-t border-outline-variant pt-4">
                            <AppDatePicker
                                v-model="form.appointed_at"
                                label="Mulai Menjabat"
                                :error="form.errors.appointed_at"
                            />
                            <AppDatePicker
                                v-model="form.term_end_at"
                                label="Selesai Menjabat"
                                :min="form.appointed_at || undefined"
                                :error="form.errors.term_end_at"
                            />
                        </div>

                        <div class="border-t border-outline-variant pt-4 space-y-3">
                            <AppCheckbox v-model="form.is_village_user" label="Tandai sebagai Operator Desa Khusus" />
                            <p class="text-xs text-on-surface-variant">Operator desa hanya memiliki hak akses terbatas untuk menginput proposal dan data anggota di desa tertentu.</p>
                            <div v-if="showVillageSelect" class="pt-2">
                                <SmartSelect
                                    v-model="form.village_row_id"
                                    label="Pilih Desa Penugasan"
                                    :options="villageOptions"
                                    searchable
                                    placeholder="Cari nama atau kode desa..."
                                    :error="form.errors.village_row_id"
                                />
                            </div>
                        </div>
                    </div>
                </AppCard>

                <div class="flex items-center justify-end gap-3">
                    <Link href="/access/users">
                        <AppButton variant="secondary" type="button">Batal</AppButton>
                    </Link>
                    <AppButton type="submit" :loading="form.processing" icon="save">
                        {{ editing ? 'Simpan Perubahan' : 'Buat Pengguna' }}
                    </AppButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
