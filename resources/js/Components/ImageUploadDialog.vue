<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import AppModal from './AppModal.vue';
import ImageAdjuster from './ImageAdjuster.vue';
import { useConfirm } from '../composables/useConfirm';
import { useI18n } from '../i18n';
import { useRemoteImageFile } from '../composables/useRemoteImageFile';
import { useToast } from '../composables/useToast';

/**
 * Dialog unggah + penyesuaian gambar yang dipakai ulang untuk foto profil
 * (lingkaran) dan logo lembaga (kotak). Setelah file dipilih, ImageAdjuster
 * otomatis terbuka agar pengguna bisa menggeser dan mengatur ukuran gambar.
 */
const open = defineModel({ type: Boolean, default: false });

const props = defineProps({
    imageUrl: { type: String, default: null },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    uploadUrl: { type: String, required: true },
    deleteUrl: { type: String, default: null },
    field: { type: String, default: 'photo' },
    shape: { type: String, default: 'circle' },
    aspect: { type: [Number, String], default: 1 },
    fit: { type: String, default: 'cover' },
    outputSize: { type: Number, default: 640 },
    outputType: { type: String, default: 'image/jpeg' },
    outputName: { type: String, default: 'photo.jpg' },
    quality: { type: Number, default: 0.92 },
    checkerboard: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: true },
    emptyIcon: { type: String, default: 'person' },
    emptyLabel: { type: String, default: 'Belum ada gambar' },
    previewLabel: { type: String, default: '' },
    hint: { type: String, default: '' },
    pickLabel: { type: String, default: 'Tarik gambar atau klik untuk pilih' },
    adjustLabel: { type: String, default: 'Sesuaikan' },
    adjustTitle: { type: String, default: 'Sesuaikan Gambar' },
    adjustSubtitle: { type: String, default: 'Geser gambar untuk mengatur posisi, lalu perbesar atau perkecil agar pas di bingkai.' },
    adjustConfirmLabel: { type: String, default: 'Gunakan Gambar' },
    uploadLabel: { type: String, default: 'Unggah' },
    deleteLabel: { type: String, default: 'Hapus' },
    deleteMessage: { type: String, default: '' },
    saveLabel: { type: String, default: 'Simpan' },
    manageUrl: { type: String, default: '' },
    manageLabel: { type: String, default: '' },
});

const { t } = useI18n();
const { confirm: confirmAction } = useConfirm();
const { loading: adjustingImage, toFile: remoteToFile } = useRemoteImageFile();
const toast = useToast();

const form = useForm({ [props.field]: null });
const preview = ref(props.imageUrl);
const imageError = ref(false);
const dragOver = ref(false);
const removeProcessing = ref(false);
const fileInput = ref(null);
const adjustOpen = ref(false);
const sourceFile = ref(null);

const hasImage = computed(() => Boolean(preview.value) && !imageError.value);
const selectedFile = computed(() => form[props.field]);

watch(() => props.imageUrl, (url) => {
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value);
    preview.value = url || null;
    imageError.value = false;
});

watch(open, (isOpen) => {
    if (!isOpen) return;
    if (!form[props.field]) preview.value = props.imageUrl || null;
    imageError.value = false;
});

function releasePreview() {
    if (preview.value?.startsWith('blob:')) URL.revokeObjectURL(preview.value);
}

onBeforeUnmount(releasePreview);

function onFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';
    setFile(file);
}

function onDrop(event) {
    event.preventDefault();
    dragOver.value = false;
    setFile(event.dataTransfer?.files?.[0] ?? null);
}

function setFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    sourceFile.value = file;
    adjustOpen.value = true;
}

function applyCropped(file) {
    releasePreview();
    form[props.field] = file;
    preview.value = URL.createObjectURL(file);
    imageError.value = false;
}

async function adjustCurrent() {
    if (selectedFile.value) {
        adjustOpen.value = true;
        return;
    }

    const file = await remoteToFile(props.imageUrl, props.outputName, props.outputType);
    if (!file) {
        toast.error('Gagal memuat gambar saat ini. Silakan pilih file dari perangkat Anda.');
        return;
    }

    sourceFile.value = file;
    adjustOpen.value = true;
}

function submit() {
    if (!selectedFile.value || form.processing) return;

    form.post(props.uploadUrl, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            open.value = false;
        },
    });
}

async function destroy() {
    if (!props.deleteUrl || removeProcessing.value) return;
    if (!await confirmAction({
        title: `${t('Hapus')} ${props.title}`,
        message: props.deleteMessage || `${t('Hapus')} ${props.title.toLowerCase()}?`,
        confirmLabel: props.deleteLabel,
        variant: 'danger',
        icon: 'delete',
    })) return;

    router.delete(props.deleteUrl, {
        preserveScroll: true,
        onStart: () => { removeProcessing.value = true; },
        onFinish: () => { removeProcessing.value = false; },
        onSuccess: () => {
            releasePreview();
            preview.value = null;
            form.reset();
            open.value = false;
        },
    });
}
</script>

<template>
    <AppModal
        v-model="open"
        :title="title"
        size="md"
        centered
        :closeable="!form.processing && !removeProcessing"
    >
        <div class="space-y-4">
            <p v-if="subtitle" class="text-sm text-on-surface-variant">{{ subtitle }}</p>

            <div class="grid gap-5 sm:grid-cols-[11rem_minmax(0,1fr)] sm:items-start">
                <div class="flex flex-col items-center gap-2">
                    <div class="relative">
                        <div
                            class="grid size-36 place-items-center overflow-hidden border border-outline-variant bg-surface-container-lowest shadow-inner"
                            :class="shape === 'circle' ? 'rounded-full' : 'rounded-2xl'"
                        >
                            <img
                                v-if="hasImage"
                                :src="preview"
                                :alt="title"
                                class="size-full"
                                :class="shape === 'circle' ? 'object-cover' : 'object-contain p-2'"
                                @error="imageError = true"
                            />
                            <div v-else class="flex flex-col items-center gap-1 px-3 text-center">
                                <AppIcon :name="emptyIcon" class="text-4xl text-on-surface-variant" />
                                <span class="text-[0.7rem] font-medium text-on-surface-variant">{{ emptyLabel }}</span>
                            </div>
                        </div>

                        <button
                            v-if="hasImage"
                            type="button"
                            class="absolute bottom-0 right-0 grid size-9 place-items-center rounded-full bg-surface-container-lowest/95 text-primary shadow ring-1 ring-outline-variant transition hover:bg-surface-container disabled:opacity-60"
                            :disabled="adjustingImage"
                            :title="adjustLabel"
                            :aria-label="adjustLabel"
                            @click="adjustCurrent"
                        >
                            <AppIcon :name="adjustingImage ? 'progress_activity' : 'tune'" class="text-lg" :class="adjustingImage && 'animate-spin'" />
                        </button>
                    </div>
                    <p v-if="previewLabel" class="text-center text-[0.7rem] font-medium uppercase tracking-[0.12em] text-on-surface-variant">
                        {{ previewLabel }}
                    </p>
                </div>

                <div class="space-y-3">
                    <label
                        class="block cursor-pointer rounded-xl border-2 border-dashed border-outline-variant bg-surface-container-lowest p-4 text-center transition-colors hover:border-primary hover:bg-surface-container-low"
                        :class="dragOver ? 'border-primary bg-primary-container/20' : ''"
                        @dragover.prevent="dragOver = true"
                        @dragleave="dragOver = false"
                        @drop="onDrop"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="sr-only"
                            @change="onFileChange"
                        />
                        <AppIcon name="upload" class="text-2xl text-on-surface-variant" />
                        <p class="mt-2 text-sm font-bold text-primary">{{ pickLabel }}</p>
                        <p v-if="hint" class="mt-1 text-xs text-on-surface-variant">{{ hint }}</p>
                    </label>

                    <div v-if="selectedFile" class="flex items-center gap-2 text-sm text-on-surface-variant">
                        <AppIcon name="image" class="shrink-0 text-base text-outline" />
                        <span class="min-w-0 flex-1 truncate">{{ selectedFile.name }}</span>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-primary transition hover:bg-surface-container"
                            @click="adjustOpen = true"
                        >
                            <AppIcon name="tune" class="text-base" />
                            {{ adjustLabel }}
                        </button>
                    </div>

                    <p v-if="form.errors[field]" class="text-sm text-error">{{ form.errors[field] }}</p>

                    <Link
                        v-if="manageUrl"
                        :href="manageUrl"
                        class="inline-flex items-center gap-1 text-xs font-bold text-primary transition hover:underline"
                        @click="open = false"
                    >
                        <AppIcon name="open_in_new" class="text-sm" />
                        {{ manageLabel }}
                    </Link>
                </div>
            </div>
        </div>

        <template #footer>
            <AppButton variant="secondary" :disabled="form.processing || removeProcessing" @click="open = false">
                {{ t('Batal') }}
            </AppButton>
            <AppButton
                v-if="imageUrl && canDelete && deleteUrl"
                type="button"
                variant="danger"
                icon="delete"
                :loading="removeProcessing"
                :disabled="form.processing || removeProcessing"
                @click="destroy"
            >
                {{ deleteLabel }}
            </AppButton>
            <AppButton
                type="button"
                icon="upload"
                :loading="form.processing"
                :disabled="!selectedFile || form.processing || removeProcessing"
                @click="submit"
            >
                {{ saveLabel || uploadLabel }}
            </AppButton>
        </template>
    </AppModal>

    <ImageAdjuster
        v-model="adjustOpen"
        :file="sourceFile"
        :title="adjustTitle"
        :subtitle="adjustSubtitle"
        :shape="shape"
        :aspect="aspect"
        :fit="fit"
        :output-size="outputSize"
        :output-type="outputType"
        :output-name="outputName"
        :quality="quality"
        :checkerboard="checkerboard"
        :confirm-label="adjustConfirmLabel"
        @confirm="applyCropped"
    />
</template>
