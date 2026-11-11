import { ref } from 'vue';

/**
 * Mengambil gambar yang sudah tersimpan (URL same-origin) dan mengubahnya
 * menjadi File agar bisa dibuka kembali di editor penyesuaian gambar.
 */
export function useRemoteImageFile() {
    const loading = ref(false);

    async function toFile(url, filename, fallbackType = 'image/jpeg') {
        if (!url) return null;

        loading.value = true;
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const blob = await response.blob();
            const type = blob.type || fallbackType;
            const extensionByType = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' };
            const extension = extensionByType[type] ?? (filename.split('.').pop() || 'jpg');
            const baseName = filename.replace(/\.[^.]+$/, '') || 'image';

            return new File([blob], `${baseName}.${extension}`, { type });
        } catch (error) {
            console.error('Gagal memuat gambar untuk disesuaikan.', error);
            return null;
        } finally {
            loading.value = false;
        }
    }

    return { loading, toFile };
}
