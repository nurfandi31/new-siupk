<script setup>
import { useConfirm } from '../../composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../composables/useCan';

const { can } = useCan();

const props = defineProps({
    asset: { type: Object, required: true },
    histories: { type: Array, required: true },
    as_of: { type: String, required: true },
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}

const { confirm: confirmAction } = useConfirm();

async function destroy() {
    if (!await confirmAction({ title: 'Hapus Inventaris', message: `Hapus inventaris "${props.asset.name}"?` })) return;
    router.delete(`/accounting/assets/${props.asset.row_id}`);
}
</script>

<template>
    <Head :title="asset.name" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="text-sm text-on-surface-variant">{{ asset.asset_code || `ID #${asset.id}` }}</p>
                    <h1 class="text-2xl font-bold text-primary">{{ asset.name }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <AppBadge :tone="asset.status === 'good' ? 'success' : asset.status === 'damaged' ? 'warning' : 'neutral'">
                            {{ asset.status_label }}
                        </AppBadge>
                        <span v-if="asset.category" class="text-sm text-on-surface-variant">{{ asset.category.name }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link href="/accounting/assets"><AppButton variant="ghost" icon="arrow_back">Daftar</AppButton></Link>
                    <Link v-if="can('assets.manage')" :href="`/accounting/assets/${asset.row_id}/edit`"><AppButton icon="edit">Edit</AppButton></Link>
                    <AppButton v-if="can('assets.manage')" variant="ghost" icon="delete" @click="destroy">Hapus</AppButton>
                </div>
            </header>

            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-surface-container-low px-4 py-3">
                    <p class="text-xs text-on-surface-variant">Nilai perolehan</p>
                    <p class="text-xl font-bold text-primary">{{ formatMoney(asset.acquisition) }}</p>
                </div>
                <div class="rounded-xl bg-surface-container-low px-4 py-3">
                    <p class="text-xs text-on-surface-variant">Akumulasi susut</p>
                    <p class="text-xl font-bold text-primary">{{ formatMoney(asset.accumulated_depreciation) }}</p>
                </div>
                <div class="rounded-xl bg-surface-container-low px-4 py-3">
                    <p class="text-xs text-on-surface-variant">Nilai buku per {{ as_of }}</p>
                    <p class="text-xl font-bold text-primary">{{ formatMoney(asset.book_value) }}</p>
                </div>
            </div>

            <AppCard>
                <h2 class="mb-4 text-sm font-bold uppercase tracking-wide text-primary">Detail</h2>
                <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-on-surface-variant">Tgl beli</dt>
                        <dd class="font-medium">{{ asset.purchased_at || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">Jumlah × harga</dt>
                        <dd class="font-medium">{{ asset.quantity }} × {{ formatMoney(asset.unit_cost) }}</dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">Umur ekonomis</dt>
                        <dd class="font-medium">
                            {{ asset.useful_life_months ? `${asset.useful_life_months} bulan` : 'Tidak disusutkan' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">Umur s.d. {{ as_of }}</dt>
                        <dd class="font-medium">{{ asset.age_months }} bulan · susut/bln {{ formatMoney(asset.monthly_depreciation) }}</dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">Unit / desa</dt>
                        <dd class="font-medium">{{ asset.unit?.name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-on-surface-variant">Validasi status</dt>
                        <dd class="font-medium">{{ asset.validated_at || '—' }}</dd>
                    </div>
                </dl>
            </AppCard>

            <AppCard :padded="false">
                <div class="border-b border-outline-variant/20 px-4 py-3">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-primary">Riwayat status</h2>
                </div>
                <div v-if="histories.length === 0" class="px-4 py-8 text-center text-sm text-on-surface-variant">
                    Belum ada riwayat.
                </div>
                <ul v-else class="divide-y divide-outline-variant/20">
                    <li v-for="h in histories" :key="h.row_id" class="px-4 py-3 text-sm">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <p class="font-medium">
                                <span v-if="h.from_label" class="text-on-surface-variant">{{ h.from_label }} → </span>
                                {{ h.to_label }}
                            </p>
                            <p class="text-xs text-on-surface-variant">{{ h.changed_at }}</p>
                        </div>
                        <p v-if="h.notes" class="mt-1 text-on-surface-variant">{{ h.notes }}</p>
                    </li>
                </ul>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
