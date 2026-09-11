<script setup>
import { computed } from 'vue';
import AppModal from '../AppModal.vue';
import { renderMarkdownHtml } from '../../composables/useMarkdown';

const props = defineProps({
    block: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const html = computed(() => (props.block ? renderMarkdownHtml(props.block.markdown) : ''));
</script>

<template>
    <AppModal
        :open="block !== null"
        :title="block?.title ?? 'Detail'"
        size="lg"
        @close="emit('close')"
    >
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="assistant-artifact-body" v-html="html" />
    </AppModal>
</template>
