<script setup>
import { useI18n } from '../i18n';
const { t } = useI18n();
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import AppModal from './AppModal.vue';

/**
 * Editor penyesuaian gambar sebelum diunggah.
 * - Geser gambar (mouse/touch) untuk mengatur posisi.
 * - Slider / tombol +/- untuk memperbesar.
 * - Hasil di-render ke canvas sesuai bingkai, lalu dikirim sebagai File baru.
 *
 * `aspect` menerima angka (mis. 1 untuk foto profil) atau string 'source'
 * agar bingkai mengikuti rasio gambar asli (cocok untuk logo).
 */
const open = defineModel({ type: Boolean, default: false });

const props = defineProps({
    file: { type: [File, Blob], default: null },
    title: { type: String, default: 'Sesuaikan Gambar' },
    subtitle: {
        type: String,
        default: 'Geser gambar untuk mengatur posisi, lalu perbesar atau perkecil agar pas di bingkai.',
    },
    shape: {
        type: String,
        default: 'square',
        validator: (value) => ['square', 'circle'].includes(value),
    },
    aspect: { type: [Number, String], default: 1 },
    fit: {
        type: String,
        default: 'cover',
        validator: (value) => ['cover', 'contain'].includes(value),
    },
    outputSize: { type: Number, default: 640 },
    outputType: { type: String, default: 'image/jpeg' },
    outputName: { type: String, default: 'image.jpg' },
    quality: { type: Number, default: 0.92 },
    maxZoom: { type: Number, default: 3 },
    checkerboard: { type: Boolean, default: false },
    confirmLabel: { type: String, default: 'Gunakan Gambar' },
});

const emit = defineEmits(['confirm']);

const frameEl = ref(null);
const frameLimit = ref(420);
const sourceUrl = ref('');
const sourceImage = shallowRef(null);
const imgW = ref(0);
const imgH = ref(0);
const frameW = ref(0);
const frameH = ref(0);
const zoom = ref(1);
const offset = ref({ x: 0, y: 0 });
const dragging = ref(false);
const processing = ref(false);

let pointerStart = null;
let resizeObserver = null;
let loadGeneration = 0;

const effectiveAspect = computed(() => {
    if (props.aspect === 'source' && imgW.value && imgH.value) {
        // Batasi rasio ekstrem agar bingkai tetap nyaman diatur.
        return Math.min(4, Math.max(0.25, imgW.value / imgH.value));
    }
    const numeric = Number(props.aspect);
    return Number.isFinite(numeric) && numeric > 0 ? numeric : 1;
});

const frameStyle = computed(() => {
    const aspect = effectiveAspect.value;
    // Batasi dimensi terbesar bingkai agar modal tidak perlu di-scroll.
    const maxWidth = aspect >= 1 ? frameLimit.value : Math.round(frameLimit.value * aspect);
    return {
        aspectRatio: `${aspect}`,
        width: `min(100%, ${Math.max(120, maxWidth)}px)`,
    };
});

function updateFrameLimit() {
    if (typeof window === 'undefined') return;
    const isWide = window.matchMedia('(min-width: 640px)').matches;
    // Ruang untuk header, footer, hint, slider, dan padding modal.
    const reserved = isWide ? 300 : 450;
    frameLimit.value = Math.round(Math.min(420, Math.max(150, window.innerHeight - reserved)));
}

onMounted(() => {
    updateFrameLimit();
    window.addEventListener('resize', updateFrameLimit);
});

const baseScale = computed(() => {
    if (!imgW.value || !imgH.value || !frameW.value || !frameH.value) return 1;
    const scaleX = frameW.value / imgW.value;
    const scaleY = frameH.value / imgH.value;
    return props.fit === 'contain' ? Math.min(scaleX, scaleY) : Math.max(scaleX, scaleY);
});

const displayW = computed(() => imgW.value * baseScale.value * zoom.value);
const displayH = computed(() => imgH.value * baseScale.value * zoom.value);

const offsetLimit = computed(() => ({
    x: Math.max(0, (displayW.value - frameW.value) / 2),
    y: Math.max(0, (displayH.value - frameH.value) / 2),
}));

const imageStyle = computed(() => ({
    width: `${displayW.value}px`,
    height: `${displayH.value}px`,
    transform: `translate(-50%, -50%) translate(${offset.value.x}px, ${offset.value.y}px)`,
}));

const zoomPercent = computed(() => Math.round(zoom.value * 100));

const visibleBounds = computed(() => {
    if (!frameW.value || !frameH.value) return { x: 0, y: 0, width: 0, height: 0 };

    const left = (frameW.value - displayW.value) / 2 + offset.value.x;
    const top = (frameH.value - displayH.value) / 2 + offset.value.y;
    const x = Math.max(0, left);
    const y = Math.max(0, top);

    return {
        x,
        y,
        width: Math.max(1, Math.min(frameW.value, left + displayW.value) - x),
        height: Math.max(1, Math.min(frameH.value, top + displayH.value) - y),
    };
});

const outputDimensions = computed(() => {
    const bounds = visibleBounds.value;
    const size = Math.max(64, props.outputSize);

    if (bounds.width < 2 || bounds.height < 2) {
        const aspect = effectiveAspect.value;
        return aspect >= 1
            ? { width: size, height: Math.max(64, Math.round(size / aspect)) }
            : { width: Math.max(64, Math.round(size * aspect)), height: size };
    }

    const scale = size / Math.max(bounds.width, bounds.height);
    return {
        width: Math.max(32, Math.round(bounds.width * scale)),
        height: Math.max(32, Math.round(bounds.height * scale)),
    };
});

const outputFormatLabel = computed(() => (props.outputType === 'image/png' ? 'PNG' : 'JPG'));

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function clampOffsets() {
    const limit = offsetLimit.value;
    offset.value = {
        x: clamp(offset.value.x, -limit.x, limit.x),
        y: clamp(offset.value.y, -limit.y, limit.y),
    };
}

function setZoom(value) {
    zoom.value = clamp(Number(value) || 1, 1, props.maxZoom);
    clampOffsets();
}

function resetTransform() {
    zoom.value = 1;
    offset.value = { x: 0, y: 0 };
}

function measure() {
    const el = frameEl.value;
    if (!el) return;
    frameW.value = el.clientWidth;
    frameH.value = el.clientHeight;
    clampOffsets();
}

function observeFrame() {
    stopObserve();
    const el = frameEl.value;
    if (!el || typeof ResizeObserver === 'undefined') return;
    resizeObserver = new ResizeObserver(() => measure());
    resizeObserver.observe(el);
}

function stopObserve() {
    resizeObserver?.disconnect();
    resizeObserver = null;
}

function releaseSourceUrl() {
    if (sourceUrl.value.startsWith('blob:')) URL.revokeObjectURL(sourceUrl.value);
    sourceUrl.value = '';
}

async function loadSource() {
    const generation = ++loadGeneration;

    releaseSourceUrl();
    sourceImage.value = null;
    imgW.value = 0;
    imgH.value = 0;
    resetTransform();

    if (!props.file) return;

    const url = URL.createObjectURL(props.file);
    sourceUrl.value = url;

    const image = await new Promise((resolve) => {
        const element = new Image();
        element.onload = () => resolve(element);
        element.onerror = () => resolve(null);
        element.src = url;
    });

    // Batalkan bila sudah ada permintaan muat lain / modal sudah ditutup.
    if (generation !== loadGeneration) return;

    if (!image) {
        releaseSourceUrl();
        return;
    }

    sourceImage.value = image;
    imgW.value = image.naturalWidth || image.width;
    imgH.value = image.naturalHeight || image.height;

    await nextTick();
    if (generation !== loadGeneration) return;
    measure();
}

watch(open, async (isOpen) => {
    if (isOpen) {
        updateFrameLimit();
        await loadSource();
        if (!open.value) return;
        await nextTick();
        measure();
        observeFrame();
        return;
    }

    loadGeneration++;
    stopObserve();
    releaseSourceUrl();
    sourceImage.value = null;
    resetTransform();
});

function onPointerDown(event) {
    if (!sourceImage.value) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    dragging.value = true;
    pointerStart = {
        x: event.clientX,
        y: event.clientY,
        offsetX: offset.value.x,
        offsetY: offset.value.y,
    };
    event.currentTarget?.setPointerCapture?.(event.pointerId);
}

function onPointerMove(event) {
    if (!dragging.value || !pointerStart) return;
    const limit = offsetLimit.value;
    offset.value = {
        x: clamp(pointerStart.offsetX + (event.clientX - pointerStart.x), -limit.x, limit.x),
        y: clamp(pointerStart.offsetY + (event.clientY - pointerStart.y), -limit.y, limit.y),
    };
}

function endDrag(event) {
    if (!dragging.value) return;
    dragging.value = false;
    pointerStart = null;
    event?.currentTarget?.releasePointerCapture?.(event.pointerId);
}

function onWheel(event) {
    if (!sourceImage.value) return;
    setZoom(zoom.value * Math.exp(-event.deltaY * 0.0015));
}

function onFrameKeydown(event) {
    const step = event.shiftKey ? 24 : 10;
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight' || event.key === 'ArrowUp' || event.key === 'ArrowDown') {
        event.preventDefault();
        const limit = offsetLimit.value;
        const delta = {
            ArrowLeft: { x: -step, y: 0 },
            ArrowRight: { x: step, y: 0 },
            ArrowUp: { x: 0, y: -step },
            ArrowDown: { x: 0, y: step },
        }[event.key];
        offset.value = {
            x: clamp(offset.value.x + delta.x, -limit.x, limit.x),
            y: clamp(offset.value.y + delta.y, -limit.y, limit.y),
        };
        return;
    }
    if (event.key === '+' || event.key === '=') {
        event.preventDefault();
        setZoom(zoom.value + 0.1);
        return;
    }
    if (event.key === '-') {
        event.preventDefault();
        setZoom(zoom.value - 0.1);
    }
}

async function confirm() {
    if (!sourceImage.value || !frameW.value || processing.value) return;
    const bounds = visibleBounds.value;
    if (bounds.width < 1 || bounds.height < 1) return;
    processing.value = true;

    try {
        const { width, height } = outputDimensions.value;
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        if (!context) return;

        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';

        if (props.outputType === 'image/jpeg') {
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, width, height);
        }

        const scaleToOutputX = width / bounds.width;
        const scaleToOutputY = height / bounds.height;
        const imageLeft = (frameW.value - displayW.value) / 2 + offset.value.x;
        const imageTop = (frameH.value - displayH.value) / 2 + offset.value.y;
        const drawW = displayW.value * scaleToOutputX;
        const drawH = displayH.value * scaleToOutputY;
        const drawX = (imageLeft - bounds.x) * scaleToOutputX;
        const drawY = (imageTop - bounds.y) * scaleToOutputY;

        context.drawImage(sourceImage.value, drawX, drawY, drawW, drawH);

        const blob = await new Promise((resolve) => {
            canvas.toBlob(resolve, props.outputType, props.quality);
        });
        if (!blob) return;

        emit('confirm', new File([blob], props.outputName, { type: props.outputType }));
        open.value = false;
    } finally {
        processing.value = false;
    }
}

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateFrameLimit);
    stopObserve();
    releaseSourceUrl();
});
</script>

<template>
    <AppModal
        v-model="open"
        :title="title"
        size="md"
        centered
    >
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_15rem] sm:items-center sm:gap-x-6 sm:gap-y-4">
            <p class="flex items-start gap-2 text-sm text-on-surface-variant sm:col-span-2">
                <AppIcon name="open_with" class="mt-0.5 shrink-0 text-base text-outline" />
                <span>{{ subtitle }}</span>
            </p>

            <div class="flex min-w-0 justify-center">
                <div
                    ref="frameEl"
                    class="relative touch-none select-none overflow-hidden bg-surface-container ring-1 ring-outline-variant focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                    :class="[
                        shape === 'circle' ? 'rounded-full' : 'rounded-2xl',
                        checkerboard && 'checkerboard',
                        sourceImage ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-default',
                    ]"
                    :style="frameStyle"
                    tabindex="0"
                    role="application"
                    :aria-label="`Area penyesuaian gambar. Geser untuk memindahkan, tombol plus dan minus untuk memperbesar. Zoom ${zoomPercent} persen.`"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="endDrag"
                    @pointercancel="endDrag"
                    @wheel.prevent="onWheel"
                    @keydown="onFrameKeydown"
                >
                    <img
                        v-if="sourceUrl"
                        :src="sourceUrl"
                        :alt="t('Pratinjau gambar')"
                        class="pointer-events-none absolute left-1/2 top-1/2 max-w-none select-none"
                        :style="imageStyle"
                        draggable="false"
                    />
                    <div v-else class="grid size-full place-items-center">
                        <AppIcon name="image" class="text-4xl text-on-surface-variant" />
                    </div>

                    <!-- Panduan komposisi -->
                    <div v-if="sourceImage && shape !== 'circle'" class="pointer-events-none absolute inset-0" aria-hidden="true">
                        <span class="absolute inset-y-0 left-1/3 w-px bg-white/40" />
                        <span class="absolute inset-y-0 left-2/3 w-px bg-white/40" />
                        <span class="absolute inset-x-0 top-1/3 h-px bg-white/40" />
                        <span class="absolute inset-x-0 top-2/3 h-px bg-white/40" />
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[0.7rem] font-bold uppercase tracking-[0.12em] text-on-surface-variant">{{ t('Ukuran') }}</span>
                        <span class="text-sm font-bold tabular-nums text-primary">{{ zoomPercent }}%</span>
                    </div>

                    <input
                        :value="zoom"
                        type="range"
                        min="1"
                        :max="maxZoom"
                        step="0.01"
                        class="zoom-range h-1.5 w-full cursor-pointer"
                        :aria-label="t('Perbesar atau perkecil gambar')"
                        @input="setZoom($event.target.value)"
                    />

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-container-low text-primary transition hover:bg-surface-container disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="zoom <= 1"
                            :aria-label="t('Perkecil gambar')"
                            @click="setZoom(zoom - 0.1)"
                        >
                            <AppIcon name="remove" class="text-lg" />
                        </button>
                        <button
                            type="button"
                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-container-low text-primary transition hover:bg-surface-container disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="zoom >= maxZoom"
                            :aria-label="t('Perbesar gambar')"
                            @click="setZoom(zoom + 0.1)"
                        >
                            <AppIcon name="add" class="text-lg" />
                        </button>
                        <button
                            type="button"
                            class="ml-auto inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-bold text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                            :title="t('Atur ulang posisi dan ukuran')"
                            @click="resetTransform"
                        >
                            <AppIcon name="restart_alt" class="text-base" />
                            {{ t('Atur ulang') }}
                        </button>
                    </div>
                </div>

                <p class="border-t border-outline-variant/60 pt-3 text-xs text-on-surface-variant">
                    {{ t('Hasil') }} {{ outputDimensions.width }} × {{ outputDimensions.height }} {{ t('px ·') }} {{ outputFormatLabel }}
                </p>
            </div>
        </div>

        <template #footer>
            <AppButton variant="secondary" :disabled="processing" @click="open = false">{{ t('Batal') }}</AppButton>
            <AppButton
                icon="check"
                :loading="processing"
                :disabled="!sourceImage || processing"
                @click="confirm"
            >
                {{ confirmLabel }}
            </AppButton>
        </template>
    </AppModal>
</template>

<style scoped>
.checkerboard {
    background-image:
        linear-gradient(45deg, color-mix(in srgb, var(--color-outline-variant) 55%, transparent) 25%, transparent 25%),
        linear-gradient(-45deg, color-mix(in srgb, var(--color-outline-variant) 55%, transparent) 25%, transparent 25%),
        linear-gradient(45deg, transparent 75%, color-mix(in srgb, var(--color-outline-variant) 55%, transparent) 75%),
        linear-gradient(-45deg, transparent 75%, color-mix(in srgb, var(--color-outline-variant) 55%, transparent) 75%);
    background-size: 16px 16px;
    background-position: 0 0, 0 8px, 8px -8px, -8px 0;
}

.zoom-range {
    accent-color: var(--color-primary);
}
</style>
