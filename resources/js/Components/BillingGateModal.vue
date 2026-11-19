<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from '../i18n';
import { useMoney } from '../composables/useMoney';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import AppModal from './AppModal.vue';
import BillingSupportContact from './BillingSupportContact.vue';

const { t } = useI18n();
const { money } = useMoney();

const model = defineModel({ type: Boolean, default: false });

const props = defineProps({
    gate: { type: Object, default: () => ({}) },
});

const invoice = computed(() => props.gate?.invoice ?? null);
const isInvoiceBlock = computed(() => props.gate?.reason === 'invoice_block');
const title = computed(() => (isInvoiceBlock.value ? t('Tagihan Belum Dilunasi') : t('Langganan Ditangguhkan')));

const targetUrl = computed(() => (invoice.value
    ? `/billing/invoices/${invoice.value.row_id}`
    : '/billing/invoices'));

const badgeLabel = computed(() => (isInvoiceBlock.value ? t('Akses Operasional Terkunci') : t('Akses Dibatasi')));

function openBilling() {
    model.value = false;
    router.visit(targetUrl.value);
}
</script>

<template>
    <AppModal v-model="model" :title="title" size="sm" centered>
        <div class="space-y-4">
            <div class="flex items-start gap-3">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-error/10 text-error">
                    <AppIcon name="lock" size="24" />
                </span>
                <div class="min-w-0 space-y-1">
                    <p class="text-xs font-bold uppercase tracking-wide text-error">{{ badgeLabel }}</p>
                    <p class="text-sm leading-relaxed text-on-surface-variant">
                        {{ gate?.message || t('Akses fitur operasional ditangguhkan sampai tagihan dilunasi.') }}
                    </p>
                </div>
            </div>

            <div v-if="invoice" class="space-y-2 rounded-xl border border-error/20 bg-error/5 p-4 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-on-surface-variant">{{ t('Nomor Tagihan') }}</span>
                    <span class="font-bold text-primary">{{ invoice.number }}</span>
                </div>
                <div v-if="invoice.description" class="flex items-start justify-between gap-3">
                    <span class="shrink-0 text-on-surface-variant">{{ t('Keterangan') }}</span>
                    <span class="text-right font-medium text-on-surface">{{ invoice.description }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-on-surface-variant">{{ t('Sisa Tagihan') }}</span>
                    <span class="font-bold text-error tabular-nums">{{ money(invoice.remaining, invoice.currency || 'IDR') }}</span>
                </div>
                <div v-if="invoice.due_at" class="flex items-center justify-between gap-3">
                    <span class="text-on-surface-variant">{{ t('Jatuh Tempo') }}</span>
                    <span class="font-semibold text-on-surface">{{ invoice.due_at }}</span>
                </div>
            </div>

            <p class="rounded-lg bg-primary/5 px-3 py-2 text-xs leading-relaxed text-on-surface-variant">
                {{ t('Akses fitur operasional akan terbuka otomatis setelah tagihan ini lunas. Anda tetap dapat mengakses halaman tagihan, profil, dan keluar dari aplikasi.') }}
            </p>

            <BillingSupportContact />
        </div>

        <template #footer>
            <AppButton variant="secondary" @click="model = false">{{ t('Nanti') }}</AppButton>
            <AppButton variant="primary" icon="receipt_long" @click="openBilling">
                {{ isInvoiceBlock ? t('Bayar Tagihan Sekarang') : t('Buka Halaman Tagihan') }}
            </AppButton>
        </template>
    </AppModal>
</template>
