<script setup>
import { useI18n } from '../../../../i18n';
const { t } = useI18n();
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AppInput from '../../../../Components/AppInput.vue';
import SmartSelect from '../../../../Components/SmartSelect.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    user: { type: Object, default: null },
    roleOptions: { type: Array, default: () => [] },
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
    role: props.user?.role || '',
});

const statusOptions = [
    { value: 'active', label: t('Aktif') },
    { value: 'suspended', label: t('Ditangguhkan') },
    { value: 'inactive', label: t('Nonaktif') },
];

function submit() {
    if (editing) {
        form.put(`/admin/tenants/${props.tenant.row_id}/users/${props.user.row_id}`);
    } else {
        form.post(`/admin/tenants/${props.tenant.row_id}/users`);
    }
}
</script>

<template>
    <Head :title="editing ? t('Edit User') : t('Tambah User')" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary">{{ editing ? t('Edit User') : t('Tambah User') }}</h1>
            </header>

            <AppCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <AppInput v-model="form.name" :label="t('Nama')" required :error="form.errors.name" />
                    <AppInput v-model="form.username" :label="t('Username')" required :error="form.errors.username" />
                    <AppInput v-model="form.email" :label="t('Email')" type="email" :error="form.errors.email" />
                    <AppInput v-model="form.phone" :label="t('Nomor HP (WhatsApp)')" icon="phone" type="tel" inputmode="tel" autocomplete="tel" required :error="form.errors.phone" />
                    <AppInput v-model="form.password" :label="editing ? t('Password baru (opsional)') : t('Password')" type="password" :required="!editing" :error="form.errors.password" />
                    <AppInput v-model="form.password_confirmation" :label="t('Konfirmasi password')" type="password" :required="!editing || !!form.password" />
                    <SmartSelect v-model="form.status" :label="t('Status')" :options="statusOptions" required :error="form.errors.status" />
                    <SmartSelect v-model="form.role" :label="t('Role')" :options="roleOptions" :error="form.errors.role" />
                    <p class="text-xs text-on-surface-variant">Role membatasi permission. Kosong = akses penuh (legacy).</p>
                    <div class="flex justify-end gap-3">
                        <Link :href="`/admin/tenants/${tenant.row_id}/users`"><AppButton variant="secondary">{{ t('Batal') }}</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">{{ t('Simpan') }}</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>
