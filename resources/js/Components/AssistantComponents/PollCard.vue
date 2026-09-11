<script setup>
import { computed, nextTick, ref } from 'vue';
import AppButton from '../AppButton.vue';
import AppIcon from '../AppIcon.vue';

const props = defineProps({
    block: { type: Object, required: true },
    submitted: { type: [String, null], default: null }, // label of selected option, '__skip__', or '__other__'
});

const emit = defineEmits(['submit']);

const showOther = ref(false);
const otherText = ref('');
const otherInput = ref(null);

const isSubmitted = computed(() => props.submitted !== null);
const isSkipped = computed(() => props.submitted === '__skip__');
const isOther = computed(() => props.submitted === '__other__');
const submittedLabel = computed(() => {
    if (typeof props.submitted === 'string' && !props.submitted.startsWith('__')) {
        return props.submitted;
    }
    return null;
});

function onPick(value) {
    if (isSubmitted.value) return;
    emit('submit', value);
}

function onSkip() {
    if (isSubmitted.value) return;
    emit('submit', '__skip__');
}

async function onOpenOther() {
    showOther.value = true;
    await nextTick();
    otherInput.value?.focus();
}

function onSubmitOther() {
    const v = otherText.value.trim();
    if (!v) return;
    emit('submit', v);
}
</script>

<template>
    <section
        class="assistant-poll-card overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest text-on-surface"
        :aria-label="`Polling: ${block.question}`"
    >
        <!-- Header: question + arrow -->
        <header class="flex items-center justify-between gap-2 px-4 pb-3 pt-4">
            <h3 class="text-sm font-semibold leading-tight">{{ block.question }}</h3>
            <span
                v-if="!isSubmitted"
                class="text-on-surface-variant"
                aria-hidden="true"
            >
                <AppIcon name="chevron_right" />
            </span>
        </header>

        <!-- Options -->
        <ol class="flex flex-col divide-y divide-outline-variant border-y border-outline-variant">
            <li
                v-for="(opt, i) in block.options"
                :key="opt.value"
                class="flex items-center gap-3 px-4 py-3 transition-all duration-150 active:scale-[0.99]"
                :class="{
                    'cursor-pointer hover:bg-primary-soft/40 active:bg-primary-soft/60': !isSubmitted,
                    'cursor-default': isSubmitted,
                    'bg-primary-soft/70': submittedLabel === opt.label,
                }"
                :aria-disabled="isSubmitted"
                :role="isSubmitted ? undefined : 'button'"
                :tabindex="isSubmitted ? -1 : 0"
                @click="onPick(opt.label)"
                @keydown.enter.prevent="onPick(opt.label)"
                @keydown.space.prevent="onPick(opt.label)"
            >
                <span
                    class="grid size-7 shrink-0 place-items-center rounded-md text-xs font-semibold transition-colors"
                    :class="submittedLabel === opt.label ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant'"
                >
                    <template v-if="submittedLabel === opt.label">
                        <AppIcon name="check" class="text-base" />
                    </template>
                    <template v-else>{{ i + 1 }}</template>
                </span>
                <span class="min-w-0 flex-1 truncate text-sm">{{ opt.label }}</span>
            </li>
        </ol>

        <!-- Bottom: Lainnya / Lewati -->
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <button
                v-if="block.allowOther && !isSubmitted && !showOther"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary-soft focus:outline-none focus:ring-2 focus:ring-primary/30"
                @click="onOpenOther"
            >
                <AppIcon name="edit" class="text-base" />
                <span>Lainnya</span>
            </button>
            <span v-else />

            <button
                v-if="!isSubmitted"
                type="button"
                class="text-xs font-semibold text-on-surface-variant transition-colors hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/30"
                @click="onSkip"
            >
                Lewati
            </button>
            <span v-else-if="isSkipped" class="text-xs italic text-on-surface-variant">— dilewati</span>
            <span v-else-if="isOther" class="text-xs italic text-on-surface-variant">✓ “{{ props.submitted }}”</span>
            <span v-else class="text-xs italic text-on-surface-variant">✓ dipilih</span>
        </div>

        <!-- Lainnya inline input -->
        <div v-if="showOther && !isSubmitted" class="flex items-center gap-2 border-t border-outline-variant bg-surface-container-low px-3 py-2">
            <input
                ref="otherInput"
                v-model="otherText"
                type="text"
                placeholder="Tulis jawaban…"
                class="min-h-9 min-w-0 flex-1 rounded-md border border-outline-variant bg-surface px-2.5 py-1.5 text-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                @keydown.enter.prevent="onSubmitOther"
            />
            <AppButton size="compact" variant="primary" :disabled="!otherText.trim()" @click="onSubmitOther">
                Kirim
            </AppButton>
        </div>

        <!-- Free-form fallback -->
        <p v-if="!isSubmitted" class="border-t border-outline-variant bg-surface-container-low px-4 py-2 text-xs text-on-surface-variant">
            Atau balas langsung…
        </p>
    </section>
</template>