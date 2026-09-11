<script setup>
import { Link } from '@inertiajs/vue3';
import AppBadge from './AppBadge.vue';

defineProps({
    loans: { type: Array, default: () => [] },
    emptyTitle: { type: String, default: 'Belum ada riwayat pinjaman' },
    emptyDescription: { type: String, default: 'Tidak ditemukan pinjaman terkait entitas ini.' },
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    if (v === null || v === undefined) return '—';
    return money.format(Number(v || 0));
}
function formatDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

const statusMeta = {
    draft: { label: 'Proposal', tone: 'neutral' },
    verified: { label: 'Verifikasi', tone: 'warning' },
    waiting: { label: 'Waiting', tone: 'warning' },
    approved: { label: 'Disetujui', tone: 'warning' },
    active: { label: 'Aktif', tone: 'success' },
    disbursed: { label: 'Aktif', tone: 'success' },
    completed: { label: 'Lunas', tone: 'primary' },
    written_off: { label: 'Hapus buku', tone: 'error' },
    rescheduled: { label: 'Reschedule', tone: 'neutral' },
};

const roleLabels = {
    borrower: 'Peminjam',
    beneficiary: 'Pemanfaat',
    'borrower+beneficiary': 'Peminjam',
    group: 'Kelompok',
};
</script>

<template>
    <div class="max-h-[28rem] overflow-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 z-10 bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                <tr>
                    <th class="px-3 py-2 text-left">Pinjaman</th>
                    <th class="px-3 py-2 text-left">Produk</th>
                    <th class="px-3 py-2 text-left">Peran</th>
                    <th class="px-3 py-2 text-right">Plafon</th>
                    <th class="px-3 py-2 text-right">Sisa Pokok</th>
                    <th class="px-3 py-2 text-left">Cair</th>
                    <th class="px-3 py-2 text-left">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="loans.length === 0">
                    <td colspan="7" class="px-3 py-10 text-center">
                        <p class="font-semibold text-on-surface">{{ emptyTitle }}</p>
                        <p class="mt-1 text-sm text-on-surface-variant">{{ emptyDescription }}</p>
                    </td>
                </tr>
                <tr
                    v-for="loan in loans"
                    :key="loan.row_id"
                    class="border-t border-outline-variant/40 transition hover:bg-surface-container-low/60"
                >
                    <td class="px-3 py-2">
                        <Link :href="loan.href" class="font-semibold text-primary hover:underline">
                            #{{ loan.id }}
                        </Link>
                        <div v-if="loan.loan_number" class="text-[10px] uppercase tracking-wide text-on-surface-variant">
                            {{ loan.loan_number }}
                        </div>
                        <div v-if="loan.group_name" class="text-xs text-on-surface-variant">
                            {{ loan.group_name }}
                        </div>
                    </td>
                    <td class="px-3 py-2">
                        <span class="font-medium">{{ (loan.product_code || '—').toUpperCase() }}</span>
                        <div class="text-xs text-on-surface-variant">{{ loan.product_name || '' }}</div>
                    </td>
                    <td class="px-3 py-2 text-on-surface-variant">
                        {{ roleLabels[loan.role] || loan.role || '—' }}
                        <div v-if="loan.allocated_amount != null" class="text-xs">
                            alokasi {{ formatMoney(loan.allocated_amount) }}
                        </div>
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(loan.principal_amount) }}</td>
                    <td class="px-3 py-2 text-right tabular-nums font-semibold">
                        {{ formatMoney(loan.principal_remaining) }}
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(loan.disbursed_at || loan.proposed_at) }}</td>
                    <td class="px-3 py-2">
                        <AppBadge :tone="(statusMeta[loan.status] || statusMeta.draft).tone">
                            {{ (statusMeta[loan.status] || { label: loan.status }).label }}
                        </AppBadge>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
