import { computed } from 'vue';
import { useI18n } from '../i18n';

/**
 * Shared month-name list and year-option generator.
 *
 * Usage:
 *   const { monthOptions, yearOptions } = usePeriodOptions();
 */
export function usePeriodOptions() {
    const { t } = useI18n();

    const monthOptions = [
        { value: '', label: t('Semua Bulan (Tahunan)') },
        { value: 1, label: t('Januari') },
        { value: 2, label: t('Februari') },
        { value: 3, label: t('Maret') },
        { value: 4, label: t('April') },
        { value: 5, label: t('Mei') },
        { value: 6, label: t('Juni') },
        { value: 7, label: t('Juli') },
        { value: 8, label: t('Agustus') },
        { value: 9, label: t('September') },
        { value: 10, label: t('Oktober') },
        { value: 11, label: t('November') },
        { value: 12, label: t('Desember') },
    ];

    const currentYear = new Date().getFullYear();

    const yearOptions = computed(() => {
        const start = currentYear - 2;
        const end = currentYear + 1;
        const result = [];
        for (let y = start; y <= end; y++) {
            result.push({ value: y, label: String(y) });
        }
        return result;
    });

    return { monthOptions, yearOptions };
}
