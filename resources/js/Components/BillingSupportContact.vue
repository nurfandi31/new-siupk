<script setup>
import { useI18n } from '../i18n';
import AppIcon from './AppIcon.vue';

const { t } = useI18n();

const WA_NUMBER = '62882006644656';
const WA_DISPLAY = '+62 882-0066-44656';
const SUPPORT_EMAIL = 'astabratamgl@gmail.com';
</script>

<template>
    <div class="rounded-lg border border-outline-variant bg-surface-container-low p-3 text-xs leading-relaxed text-on-surface-variant">
        <p class="font-semibold text-on-surface">{{ t('Untuk info lebih lanjut, hubungi Technical Support:') }}</p>
        <div class="mt-1.5 flex flex-wrap gap-x-4 gap-y-1.5">
            <a
                :href="`https://wa.me/${WA_NUMBER}`"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 font-semibold text-primary hover:underline"
            >
                <AppIcon name="call" size="14" />
                {{ t('WhatsApp') }} {{ WA_DISPLAY }}
            </a>
            <a
                :href="`mailto:${SUPPORT_EMAIL}`"
                class="inline-flex items-center gap-1 font-semibold text-primary hover:underline"
            >
                <AppIcon name="mail" size="14" />
                {{ SUPPORT_EMAIL }}
            </a>
        </div>
    </div>
</template>
