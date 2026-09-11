<script setup>
import AppCurrencyInput from './AppCurrencyInput.vue';
import { useMoney } from '../composables/useMoney';

defineProps({
    beneficiaries: { type: Array, required: true },
    amountField: { type: String, required: true },
    label: { type: String, required: true },
    total: { type: Number, required: true },
    maxAmount: { type: Number, default: null },
    errorFor: { type: Function, default: null },
});

defineEmits(['update-amount']);

const { money } = useMoney();
</script>

<template>
    <div v-if="beneficiaries.length" class="overflow-hidden rounded-xl border border-outline-variant">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-container-low text-xs uppercase tracking-wider text-on-surface-variant">
                <tr>
                    <th class="px-4 py-3">Anggota</th>
                    <th class="px-4 py-3 text-right">{{ label }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant">
                <tr v-for="beneficiary in beneficiaries" :key="beneficiary.row_id ?? beneficiary.member_row_id">
                    <td class="px-4 py-3">
                        <p class="font-semibold text-primary">{{ beneficiary.name || '—' }}</p>
                        <p class="text-xs text-on-surface-variant">{{ beneficiary.nik || '' }} · #{{ beneficiary.member_id ?? beneficiary.member_row_id }}</p>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <AppCurrencyInput
                            :model-value="beneficiary[amountField]"
                            label=""
                            hide-label
                            :min="0"
                            :max="maxAmount"
                            :error="errorFor?.(beneficiary)"
                            @update:model-value="(value) => $emit('update-amount', beneficiary.member_row_id, value)"
                        />
                    </td>
                </tr>
            </tbody>
            <tfoot class="bg-surface-container-low">
                <tr>
                    <td class="px-4 py-3 text-right text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total</td>
                    <td class="px-4 py-3 text-right font-bold text-primary">{{ money(total) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
