<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    width: { type: Number, default: 560 },
    height: { type: Number, default: 220 },
    strokeColor: { type: String, default: '#0f172a' },
    strokeWidth: { type: Number, default: 2.5 },
});

const emit = defineEmits(['save', 'cancel']);

const canvasRef = ref(null);
const isDrawing = ref(false);
const hasDrawn = ref(false);
let ctx = null;

function getCoordinates(event) {
    const canvas = canvasRef.value;
    if (!canvas) return { x: 0, y: 0 };
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;

    if (event.touches && event.touches.length > 0) {
        return {
            x: (event.touches[0].clientX - rect.left) * scaleX,
            y: (event.touches[0].clientY - rect.top) * scaleY,
        };
    }
    return {
        x: (event.clientX - rect.left) * scaleX,
        y: (event.clientY - rect.top) * scaleY,
    };
}

function startDrawing(event) {
    if (!ctx) return;
    event.preventDefault();
    isDrawing.value = true;
    const { x, y } = getCoordinates(event);
    ctx.beginPath();
    ctx.moveTo(x, y);
}

function draw(event) {
    if (!isDrawing.value || !ctx) return;
    event.preventDefault();
    const { x, y } = getCoordinates(event);
    ctx.lineTo(x, y);
    ctx.stroke();
    hasDrawn.value = true;
}

function stopDrawing() {
    if (!isDrawing.value || !ctx) return;
    isDrawing.value = false;
    ctx.closePath();
}

function clear() {
    if (!ctx || !canvasRef.value) return;
    ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height);
    hasDrawn.value = false;
}

function save() {
    if (!canvasRef.value || !hasDrawn.value) return;
    // Export transparent PNG
    const dataUrl = canvasRef.value.toDataURL('image/png');
    emit('save', dataUrl);
}

function initCanvas() {
    const canvas = canvasRef.value;
    if (!canvas) return;
    ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = props.strokeColor;
    ctx.lineWidth = props.strokeWidth;
}

onMounted(() => {
    initCanvas();
});

watch(() => props.strokeColor, (c) => {
    if (ctx) ctx.strokeStyle = c;
});

defineExpose({ clear, save });
</script>

<template>
    <div class="space-y-4">
        <div class="relative overflow-hidden rounded-xl border-2 border-dashed border-outline-variant bg-white p-2">
            <canvas
                ref="canvasRef"
                :width="width"
                :height="height"
                class="w-full touch-none cursor-crosshair bg-transparent"
                @mousedown="startDrawing"
                @mousemove="draw"
                @mouseup="stopDrawing"
                @mouseleave="stopDrawing"
                @touchstart="startDrawing"
                @touchmove="draw"
                @touchend="stopDrawing"
            />
            <div
                v-if="!hasDrawn"
                class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-on-surface-variant select-none"
            >
                <div class="flex items-center gap-2">
                    <AppIcon name="draw" class="text-on-surface-variant" />
                    <span>Goreskan tanda tangan di sini</span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <div class="flex items-center gap-2">
                <AppButton
                    type="button"
                    variant="ghost"
                    size="compact"
                    icon="refresh"
                    :disabled="!hasDrawn"
                    @click="clear"
                >
                    Hapus Ulang
                </AppButton>
            </div>
            <div class="flex items-center gap-2">
                <AppButton
                    type="button"
                    variant="secondary"
                    size="compact"
                    @click="$emit('cancel')"
                >
                    Batal
                </AppButton>
                <AppButton
                    type="button"
                    variant="primary"
                    size="compact"
                    icon="check"
                    :disabled="!hasDrawn"
                    @click="save"
                >
                    Gunakan Tanda Tangan
                </AppButton>
            </div>
        </div>
    </div>
</template>
