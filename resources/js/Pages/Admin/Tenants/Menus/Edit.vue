<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AppIcon from '../../../../Components/AppIcon.vue';
import AppIconButton from '../../../../Components/AppIconButton.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useI18n } from '../../../../i18n';

const { t } = useI18n();

const props = defineProps({
    tenant: { type: Object, required: true },
    role: { type: Object, required: true },
    menuGroups: { type: Array, default: () => [] },
    totalMenus: { type: Number, default: 0 },
});

const form = useForm({
    restricted: !!props.role.restricted,
    menus: [...(props.role.selected || [])],
});

const selectedCount = computed(() => form.menus.length);

function isChecked(key) {
    return form.menus.includes(key);
}

function toggleMenu(key) {
    const index = form.menus.indexOf(key);
    if (index === -1) {
        form.menus.push(key);
    } else {
        form.menus.splice(index, 1);
    }
}

function groupKeys(group) {
    return group.items.map((item) => item.key);
}

function isGroupAllSelected(group) {
    const keys = groupKeys(group);
    return keys.length > 0 && keys.every((key) => form.menus.includes(key));
}

function toggleGroup(group) {
    const keys = groupKeys(group);
    if (isGroupAllSelected(group)) {
        form.menus = form.menus.filter((key) => !keys.includes(key));
        return;
    }

    const merged = new Set([...form.menus, ...keys]);
    form.menus = [...merged];
}

function selectAll() {
    form.menus = props.menuGroups.flatMap(groupKeys);
}

function clearAll() {
    form.menus = [];
}

function submit() {
    form.put(`/admin/tenants/${props.tenant.row_id}/menus/${props.role.row_id}`);
}
</script>

<template>
    <Head :title="`Hak Akses Menu · ${role.name}`" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-4 sm:space-y-6">
            <header class="space-y-3">
                <Link :href="`/admin/tenants/${tenant.row_id}/menus`" class="inline-flex items-center gap-1 text-sm font-semibold text-primary transition hover:text-primary-container">
                    <AppIconButton name="arrow_back" size="sm" tone="primary" rounded="full" :aria-label="t('Kembali')" />
                    <span>{{ t('Hak Akses Menu') }}</span>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-primary">{{ role.name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ tenant.name }} · <code class="font-mono text-xs">{{ role.code }}</code>
                    </p>
                </div>
            </header>

            <div v-if="role.is_locked" class="flex items-start gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-primary">
                <AppIcon name="lock" class="mt-0.5 text-xl" />
                <div class="space-y-1">
                    <p class="font-bold">{{ t('Role Administrator Terkunci') }}</p>
                    <p class="text-sm text-on-surface-variant">
                        {{ t('Role Administrator selalu memiliki akses penuh ke seluruh menu dan tidak dapat dibatasi.') }}
                    </p>
                </div>
            </div>

            <form v-else class="space-y-6" @submit.prevent="submit">
                <AppCard :title="t('Mode Akses Menu')">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-outline-variant p-4 transition-colors hover:bg-surface-container-lowest">
                        <input
                            v-model="form.restricted"
                            type="checkbox"
                            class="mt-1 size-4 rounded border-outline text-primary focus:ring-primary"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-on-surface">{{ t('Batasi menu untuk role ini') }}</span>
                            <span class="mt-0.5 block text-xs text-on-surface-variant">
                                {{ t('Jika dimatikan, role mengikuti izin modul seperti biasa. Jika dinyalakan, hanya menu yang dicentang yang tampil dan bisa dibuka.') }}
                            </span>
                        </span>
                    </label>
                </AppCard>

                <div v-if="form.restricted" class="space-y-4">
                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <div>
                            <h2 class="text-lg font-bold text-primary">{{ t('Daftar Menu') }}</h2>
                            <p class="text-xs text-on-surface-variant">
                                {{ selectedCount }} / {{ totalMenus }} {{ t('menu dipilih') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <AppButton variant="secondary" size="compact" type="button" @click="selectAll">{{ t('Pilih Semua') }}</AppButton>
                            <AppButton variant="ghost" size="compact" type="button" @click="clearAll">{{ t('Kosongkan') }}</AppButton>
                        </div>
                    </div>

                    <AppCard v-for="group in menuGroups" :key="group.key" :padded="false">
                        <div class="flex items-center justify-between border-b border-outline-variant px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <AppIcon name="menu" class="text-lg text-primary" />
                                <span class="font-bold text-primary">{{ t(group.label) }}</span>
                                <span class="rounded-full bg-surface-container-low px-2 py-0.5 text-[10px] font-bold text-on-surface-variant">
                                    {{ group.items.filter((item) => isChecked(item.key)).length }} / {{ group.items.length }}
                                </span>
                            </div>
                            <button type="button" class="text-xs font-semibold text-primary hover:underline" @click="toggleGroup(group)">
                                {{ isGroupAllSelected(group) ? t('Batalkan Semua') : t('Pilih Grup Ini') }}
                            </button>
                        </div>
                        <div class="grid gap-2 p-4 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="item in group.items"
                                :key="item.key"
                                class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-outline-variant p-3 transition-colors hover:bg-surface-container-lowest"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isChecked(item.key)"
                                    class="mt-0.5 size-4 rounded border-outline text-primary focus:ring-primary"
                                    @change="toggleMenu(item.key)"
                                />
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-on-surface">{{ t(item.label) }}</span>
                                    <code class="block truncate font-mono text-[10px] text-outline">{{ item.path }}</code>
                                </span>
                            </label>
                        </div>
                    </AppCard>
                </div>

                <div v-else class="flex items-start gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-sm text-on-surface-variant">
                    <AppIcon name="info" class="mt-0.5 text-lg text-primary" />
                    <p>
                        {{ t('Role ini masih memakai semua menu. Nyalakan "Batasi menu untuk role ini" untuk memilih menu yang boleh diakses.') }}
                    </p>
                </div>

                <div class="flex flex-col-reverse items-stretch justify-end gap-3 sm:flex-row sm:items-center">
                    <Link :href="`/admin/tenants/${tenant.row_id}/menus`">
                        <AppButton variant="secondary" type="button" class="w-full sm:w-auto">{{ t('Batal') }}</AppButton>
                    </Link>
                    <AppButton type="submit" :loading="form.processing" icon="save">{{ t('Simpan Hak Akses Menu') }}</AppButton>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
