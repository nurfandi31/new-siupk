const MAX_LENGTH = 26;

const FALLBACKS = {
    input: 'Masukkan nilai',
    currency: 'Masukkan nominal',
    textarea: 'Masukkan catatan',
    select: 'Pilih opsi',
    date: 'Pilih tanggal',
};

/**
 * Placeholder default yang ringkas supaya teks di dalam input tidak panjang.
 * Label tetap tampil di atas field, jadi placeholder cukup jadi petunjuk singkat.
 */
export function defaultPlaceholder(kind, label) {
    const cleanLabel = String(label ?? '')
        .replace(/\s*\([^)]*\)\s*/g, ' ')
        .replace(/^(pilih|cari)\s+/i, '')
        .replace(/\s+/g, ' ')
        .trim();

    const verb = kind === 'select' || kind === 'date' ? 'Pilih' : 'Masukkan';
    const candidate = `${verb} ${cleanLabel.toLowerCase()}`;

    return candidate.length <= MAX_LENGTH ? candidate : (FALLBACKS[kind] ?? FALLBACKS.input);
}
