<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    filters: { type: Object, required: true },
    type_options: { type: Array, required: true },
    counts: { type: Object, required: true },
});

const q = ref(props.filters.q || '');
const type = ref(props.filters.type || 'all');
const status = ref(props.filters.status || 'all');
const syncing = ref(false);

watch(
    () => props.filters,
    (f) => {
        syncing.value = true;
        q.value = f.q || '';
        type.value = f.type || 'all';
        status.value = f.status || 'all';
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
    { deep: true },
);

const statusOptions = [
    { value: 'all', label: 'Semua status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

function apply() {
    if (syncing.value) return;
    router.get(
        '/accounting/chart-of-accounts',
        {
            q: q.value || undefined,
            type: type.value === 'all' ? undefined : type.value,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveState: false, preserveScroll: true, replace: true },
    );
}

let searchTimer;
watch(q, () => {
    if (syncing.value) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => apply(), 350);
});
watch([type, status], () => {
    if (syncing.value) return;
    apply();
});
</script>

<template>
    <Head title="Bagan Akun" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Bagan Akun</h1>
                    <p class="mt-1 text-on-surface-variant">
                        Tampilan saja. Tambah/ubah/hapus akun hanya lewat persetujuan pusat.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3 text-sm">
                    <div class="rounded-xl bg-surface-container-low px-3 py-2">
                        <span class="text-on-surface-variant">Total</span>
                        <span class="ml-2 font-bold text-primary">{{ counts.total }}</span>
                    </div>
                    <div class="rounded-xl bg-surface-container-low px-3 py-2">
                        <span class="text-on-surface-variant">Aktif</span>
                        <span class="ml-2 font-bold text-primary">{{ counts.active }}</span>
                    </div>
                    <div class="rounded-xl bg-surface-container-low px-3 py-2">
                        <span class="text-on-surface-variant">Bisa di-post</span>
                        <span class="ml-2 font-bold text-primary">{{ counts.postable }}</span>
                    </div>
                </div>
            </header>

            <AppCard>
                <div class="grid gap-3 sm:grid-cols-3">
                    <AppInput v-model="q" label="Cari" placeholder="Kode atau nama akun" />
                    <SmartSelect v-model="type" label="Jenis" :options="type_options" />
                    <SmartSelect v-model="status" label="Status" :options="statusOptions" />
                </div>
            </AppCard>

            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-surface-container-low text-left text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Kode</th>
                                <th class="px-4 py-3 font-semibold">Nama</th>
                                <th class="px-4 py-3 font-semibold">Jenis</th>
                                <th class="px-4 py-3 font-semibold">Saldo normal</th>
                                <th class="px-4 py-3 font-semibold">Posting</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold">Tgl Ditambah</th>
                                <th class="px-4 py-3 font-semibold">Tgl Nonaktif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="8" class="px-4 py-10 text-center text-on-surface-variant">
                                    Tidak ada akun yang cocok.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.row_id"
                                class="border-t border-outline-variant/20"
                                :class="row.is_active ? '' : 'opacity-60'"
                            >
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs font-semibold text-primary">
                                    {{ row.code }}
                                </td>
                                <td class="px-4 py-2.5" :style="{ paddingLeft: `${12 + Math.max(0, row.level - 1) * 16}px` }">
                                    <span :class="row.is_postable ? 'font-medium' : 'font-semibold text-on-surface'">
                                        {{ row.name }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-on-surface-variant">
                                    {{ row.type_label }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span class="font-mono text-xs">{{ row.normal_balance === 'D' ? 'Debit' : 'Kredit' }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <AppBadge :tone="row.is_postable ? 'success' : 'neutral'">
                                        {{ row.is_postable ? 'Ya' : 'Header' }}
                                    </AppBadge>
                                </td>
                                <td class="px-4 py-2.5">
                                    <AppBadge :tone="row.is_active ? 'success' : 'warning'">
                                        {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </AppBadge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">
                                    {{ row.created_at || '—' }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-4 py-2.5 font-mono text-xs"
                                    :class="row.deactivated_at ? 'text-on-surface-variant' : 'text-outline'"
                                >
                                    {{ row.deactivated_at || '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-outline-variant/20 px-4 py-3 text-xs text-on-surface-variant">
                    {{ rows.length }} baris ditampilkan
                    <span v-if="filters.q || filters.type || filters.status !== 'all'"> (terfilter)</span>.
                </p>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
