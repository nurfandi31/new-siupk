<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

defineProps({
    reports: { type: Array, required: true },
});
</script>

<template>
    <Head title="Pelaporan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Keuangan</p>
                <h1 class="mt-1 text-2xl font-bold text-primary">Pelaporan Akuntansi</h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    Laporan dari jurnal posted. Sumber kebenaran = baris jurnal, bukan salinan saldo legacy.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="report in reports"
                    :key="report.key"
                    :href="report.href"
                    class="block transition hover:-translate-y-0.5"
                >
                    <AppCard class="h-full p-5 hover:shadow-md">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <AppIcon :name="report.icon" class="text-xl" />
                            </div>
                            <div>
                                <h2 class="font-semibold text-on-surface">{{ report.title }}</h2>
                                <p class="mt-1 text-xs text-on-surface-variant">Buka laporan</p>
                            </div>
                        </div>
                    </AppCard>
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
