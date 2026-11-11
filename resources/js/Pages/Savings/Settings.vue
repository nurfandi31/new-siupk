<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import SavingsSettingsPanel from '../../Components/Savings/SettingsPanel.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import { useI18n } from '../../i18n';

const { t } = useI18n();

const props = defineProps({
    settings: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    module: { type: Object, default: () => ({}) },
    methods: { type: Array, default: () => [] },
    categories: { type: Object, default: () => ({}) },
    formTypes: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Head :title="t('Pengaturan Simpanan')" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-bold text-primary">{{ t('Pengaturan Simpanan') }}</h1>
                        <AppBadge tone="success-soft">{{ t('Modul Lokasi') }}</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">
                        {{ t('Atur SOP bunga, pajak, biaya, metode saldo, dan jenis simpanan untuk lokasi ini.') }}
                    </p>
                </div>
                <AppButton variant="secondary" icon="settings" @click="router.visit('/settings?tab=savings')">
                    {{ t('Buka di Pengaturan') }}
                </AppButton>
            </header>

            <SavingsSettingsPanel
                :settings="props.settings"
                :products="props.products"
                :module="props.module"
                :methods="props.methods"
                :categories="props.categories"
                :form-types="props.formTypes"
            />
        </div>
    </AuthenticatedLayout>
</template>
