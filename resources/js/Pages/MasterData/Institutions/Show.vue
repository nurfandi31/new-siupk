<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import LoanHistoryTable from '../../../Components/LoanHistoryTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

defineProps({
    institution: { type: Object, required: true },
    loans: { type: Array, default: () => [] },
    summary: { type: Object, required: true },
    loan_note: { type: String, default: null },
});
</script>

<template>
    <Head :title="`Lembaga · ${institution.name}`" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <Link href="/master-data/institutions" class="text-sm font-semibold text-primary hover:underline">
                        ← Daftar lembaga
                    </Link>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-primary">{{ institution.name }}</h1>
                        <AppBadge :tone="institution.is_active ? 'success' : 'neutral'">
                            {{ institution.is_active ? 'Aktif' : 'Nonaktif' }}
                        </AppBadge>
                    </div>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ institution.code }}
                        <span v-if="institution.village?.name"> · {{ institution.village.name }}</span>
                    </p>
                </div>
                <Link :href="`/master-data/institutions/${institution.row_id}/edit`">
                    <AppButton variant="secondary" icon="edit" size="compact">Edit</AppButton>
                </Link>
            </header>

            <div class="grid gap-6 lg:grid-cols-5">
                <AppCard class="lg:col-span-2 p-5">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">Profil</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">No. identitas</dt>
                            <dd class="font-medium">{{ institution.institution_identity_number || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">Pimpinan</dt>
                            <dd class="font-medium">{{ institution.leader_name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">Penanggung jawab</dt>
                            <dd class="font-medium">{{ institution.responsible_name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">No. HP</dt>
                            <dd class="font-medium">{{ institution.phone || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant">Desa</dt>
                            <dd class="font-medium">{{ institution.village?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-on-surface-variant">Alamat</dt>
                            <dd class="mt-1 font-medium">{{ institution.address || '—' }}</dd>
                        </div>
                    </dl>
                </AppCard>

                <AppCard class="lg:col-span-3 overflow-hidden p-0">
                    <div class="border-b border-outline-variant px-5 py-4">
                        <h2 class="font-bold text-primary">Riwayat pinjaman</h2>
                        <p v-if="loan_note" class="mt-1 text-xs text-on-surface-variant">{{ loan_note }}</p>
                    </div>
                    <LoanHistoryTable
                        :loans="loans"
                        empty-title="Belum ada riwayat pinjaman"
                        empty-description="Lembaga lain belum terhubung ke modul pinjaman pada skema saat ini."
                    />
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
