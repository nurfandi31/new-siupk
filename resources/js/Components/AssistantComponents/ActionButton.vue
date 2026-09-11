<script setup>
import AppButton from '../AppButton.vue';

const props = defineProps({
    block: { type: Object, required: true },
});

const emit = defineEmits(['submit']);

function onClick() {
    if (props.block.url) {
        if (props.block.icon === 'download' || props.block.download || props.block.url.includes('download=1')) {
            const link = document.createElement('a');
            link.href = props.block.url;
            link.target = '_blank';
            link.setAttribute('download', '');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            return;
        }

        window.open(props.block.url, props.block.target || '_blank', 'noopener,noreferrer');
        return;
    }
    if (props.block.value) emit('submit', props.block.value);
}
</script>

<template>
    <div class="flex">
        <AppButton
            size="compact"
            :variant="block.url ? 'outline' : 'primary'"
            :icon="block.icon || (block.url ? 'open_in_new' : 'check')"
            class="w-full !justify-start"
            @click="onClick"
        >
            {{ block.label }}
        </AppButton>
    </div>
</template>