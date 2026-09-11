<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    role: { type: Object, default: null },
    permissionGroups: { type: Array, required: true },
});

const editing = Boolean(props.role);
const isLocked = Boolean(props.role?.is_locked);
const isSystem = Boolean(props.role?.is_system);

const form = useForm({
    name: props.role?.name || '',
    code: props.role?.code || '',
    description: props.role?.description || '',
    permissions: props.role?.permissions ? [...props.role.permissions] : [],
});

function isGroupAllSelected(group) {
    if (isLocked) return true;
    return group.permissions.every((p) => form.permissions.includes(p.key));
}

function toggleGroup(group) {
    if (isLocked) return;
    const groupKeys = group.permissions.map((p) => p.key);
    const allSelected = isGroupAllSelected(group);

    if (allSelected) {
        // Deselect all in group
        form.permissions = form.permissions.filter((k) => !groupKeys.includes(k));
    } else {
        // Select all in group
        const toAdd = groupKeys.filter((k) => !form.permissions.includes(k));
        form.permissions = [...form.permissions, ...toAdd];
    }
}

function togglePermission(key) {
    if (isLocked) return;
    const idx = form.permissions.indexOf(key);
    if (idx >= 0) {
        form.permissions.splice(idx, 1);
    } else {
        form.permissions.push(key);
    }
}

function selectAllPermissions() {
    if (isLocked) return;
    const all = [];
    props.permissionGroups.forEach((g) => {
        g.permissions.forEach((p) => all.push(p.key));
    });
    form.permissions = all;
}

function clearAllPermissions() {
    if (isLocked) return;
    form.permissions = [];
}

function submit() {
    if (isLocked) return;
    if (editing) {
        form.put(`/access/roles/${props.role.row_id}`);
    } else {
        form.post('/access/roles');
    }
}
</script>

<template>
    <Head :title="editing ? `Role: ${role.name}` : 'Tambah Role Kustom'" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <header>
                <Link href="/access/roles" class="text-sm font-semibold text-primary hover:underline">
                    ← Kembali ke Daftar Role
                </Link>
                <div class="mt-2 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-primary sm:text-3xl">
                            {{ editing ? `Role: ${role.name}` : 'Tambah Role Kustom Baru' }}
                        </h1>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            Tentukan perizinan akses modul dan fitur yang dapat diakses oleh pemegang peran ini.
                        </p>
                    </div>
                    <div v-if="editing" class="flex items-center gap-2">
                        <AppBadge v-if="isLocked" tone="primary">Terkunci (Admin)</AppBadge>
                        <AppBadge v-else-if="isSystem" tone="neutral">Bawaan Sistem</AppBadge>
                        <AppBadge v-else tone="info">Kustom</AppBadge>
                    </div>
                </div>
            </header>

            <!-- Locked Admin Banner -->
            <div v-if="isLocked" class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-primary">
                <AppIcon name="lock" class="mt-0.5 text-xl text-primary" />
                <div class="space-y-1">
                    <p class="font-bold">Role Administrator Terkunci Penuh</p>
                    <p class="text-sm text-on-surface-variant">
                        Role Administrator memiliki wewenang penuh (*) ke seluruh modul dan tidak dapat diubah izinnya demi keamanan sistem.
                    </p>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <AppCard title="Identitas Role">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput
                            v-model="form.name"
                            label="Nama Role"
                            placeholder="Contoh: Petugas Lapangan"
                            required
                            :readonly="isLocked"
                            :error="form.errors.name"
                        />
                        <AppInput
                            v-model="form.code"
                            label="Kode Role (Huruf kecil, angka, atau underscore)"
                            placeholder="Contoh: petugas_lapangan"
                            required
                            :readonly="isLocked || isSystem"
                            :error="form.errors.code"
                        />
                        <div class="sm:col-span-2">
                            <AppInput
                                v-model="form.description"
                                label="Deskripsi / Catatan Peran (Opsional)"
                                placeholder="Jelaskan tanggung jawab atau wewenang peran ini..."
                                :readonly="isLocked"
                                :error="form.errors.description"
                            />
                        </div>
                    </div>
                </AppCard>

                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Matriks Hak Akses (Permissions)</h2>
                        <p class="text-xs text-on-surface-variant">Centang izin akses yang diberikan untuk peran ini.</p>
                    </div>
                    <div v-if="!isLocked" class="flex items-center gap-2">
                        <AppButton variant="secondary" size="compact" type="button" @click="selectAllPermissions">Pilih Semua</AppButton>
                        <AppButton variant="ghost" size="compact" type="button" @click="clearAllPermissions">Kosongkan</AppButton>
                    </div>
                </div>

                <div class="space-y-6">
                    <AppCard v-for="group in permissionGroups" :key="group.category" :title="group.label">
                        <template #header>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <AppIcon :name="group.icon" class="text-xl text-primary" />
                                    <span class="font-bold text-primary">{{ group.label }}</span>
                                </div>
                                <button
                                    v-if="!isLocked"
                                    type="button"
                                    class="text-xs font-semibold text-primary hover:underline"
                                    @click="toggleGroup(group)"
                                >
                                    {{ isGroupAllSelected(group) ? 'Batalkan Semua' : 'Pilih Grup Ini' }}
                                </button>
                            </div>
                        </template>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="perm in group.permissions"
                                :key="perm.key"
                                class="flex items-start gap-3 rounded-lg border border-outline-variant p-3 transition-colors hover:bg-surface-container-lowest"
                                :class="{ 'opacity-80': isLocked, 'cursor-pointer': !isLocked }"
                                @click="togglePermission(perm.key)"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isLocked || form.permissions.includes(perm.key)"
                                    :disabled="isLocked"
                                    class="mt-1 size-4 rounded border-outline text-primary focus:ring-primary"
                                    @click.stop="togglePermission(perm.key)"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-on-surface">{{ perm.label }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ perm.description }}</p>
                                    <code class="mt-0.5 inline-block font-mono text-[10px] text-outline">{{ perm.key }}</code>
                                </div>
                            </div>
                        </div>
                    </AppCard>
                </div>

                <div v-if="!isLocked" class="flex items-center justify-end gap-3 pt-2">
                    <Link href="/access/roles">
                        <AppButton variant="secondary" type="button">Batal</AppButton>
                    </Link>
                    <AppButton type="submit" :loading="form.processing" icon="save">
                        {{ editing ? 'Simpan Perubahan Hak Akses' : 'Buat Role' }}
                    </AppButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
