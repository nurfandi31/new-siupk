<script setup lang="ts">
import { useI18n, dateLocale } from '../../../i18n';
const { t } = useI18n();
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useConfirm } from '../../../composables/useConfirm';

const { confirm } = useConfirm();

interface NotePayload {
    row_id: number;
    public_id: string;
    supervisor_user_id: number;
    period_year: number;
    period_month: number | null;
    period_label: string;
    category: string;
    category_label: string;
    subject: string;
    content: string;
    status: string;
    status_label: string;
    submitted_at: string | null;
    acknowledged_at: string | null;
    acknowledgment_note: string | null;
    supervisor: { row_id: number; name: string } | null;
    acknowledger: { row_id: number; name: string } | null;
    created_at: string | null;
    updated_at: string | null;
}

interface CanBag {
    edit: boolean;
    submit: boolean;
    acknowledge: boolean;
    delete: boolean;
}

const props = defineProps<{
    note: NotePayload;
    identity: { legal_name: string; short_name: string | null };
    categories: Record<string, string>;
    statuses: Record<string, string>;
    can: CanBag;
    monthLabels: Record<string, string>;
}>();

const ackForm = useForm({
    acknowledgment_note: '',
});
const showAckForm = ref<boolean>(false);

const isDraft = computed<boolean>(() => props.note.status === 'draft');
const isSubmitted = computed<boolean>(() => props.note.status === 'submitted');
const isAcknowledged = computed<boolean>(() => props.note.status === 'acknowledged');

function statusTone(status: string): string {
    if (status === 'acknowledged') return 'success';
    if (status === 'submitted') return 'info-soft';
    return 'neutral';
}

function submitForReview(): void {
    router.post(`/supervisor/notes/${props.note.row_id}/submit`, {}, { preserveScroll: true });
}

function acknowledge(): void {
    router.post(`/supervisor/notes/${props.note.row_id}/acknowledge`, ackForm.data(), {
        preserveScroll: true,
        onSuccess: () => {
            showAckForm.value = false;
            ackForm.reset();
        },
    });
}

async function remove(): Promise<void> {
    const ok = await confirm({
        title: t('Hapus Catatan Pengawas'),
        message: t('Catatan ini akan dihapus permanen dan tidak dapat dikembalikan.'),
        confirmLabel: t('Ya, Hapus'),
        variant: 'danger',
    });
    if (!ok) return;
    router.delete(`/supervisor/notes/${props.note.row_id}`, { preserveScroll: true });
}

function formatDateTime(value: string | null): string {
    if (!value) return '—';
    try {
        return new Date(value).toLocaleString(dateLocale(), { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return value;
    }
}
</script>

<template>
    <Head :title="note.subject" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        {{ t('Pengawas') }}
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">{{ note.subject }}</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ note.period_label }} · {{ note.category_label }} · {{ identity.legal_name }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        href="/supervisor/notes"
                        class="inline-flex h-10 items-center rounded-xl border border-outline-variant px-4 text-sm font-semibold text-primary hover:bg-surface-container-low"
                    >
                        {{ t('← Daftar') }}
                    </a>
                    <AppButton
                        v-if="can.edit"
                        tag="a"
                        :href="`/supervisor/notes/${note.row_id}/edit`"
                        icon="edit"
                        size="compact"
                    >
                        {{ t('Edit') }}
                    </AppButton>
                    <AppButton
                        v-if="can.submit"
                        type="button"
                        icon="send"
                        size="compact"
                        @click="submitForReview"
                    >
                        {{ t('Submit') }}
                    </AppButton>
                </div>
            </div>

            <AppCard class="p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <AppBadge :tone="statusTone(note.status)">{{ note.status_label }}</AppBadge>
                        <span class="text-xs text-on-surface-variant">
                            {{ t('Dibuat:') }} {{ formatDateTime(note.created_at) }}
                        </span>
                        <span v-if="note.updated_at && note.updated_at !== note.created_at" class="text-xs text-on-surface-variant">
                            {{ t('· Diubah:') }} {{ formatDateTime(note.updated_at) }}
                        </span>
                    </div>
                    <div class="text-xs text-on-surface-variant">
                        <p v-if="note.supervisor">
                            {{ t('Pengawas:') }} <b>{{ note.supervisor.name }}</b>
                        </p>
                        <p v-if="note.acknowledger">
                            {{ t('Disetujui oleh:') }} <b>{{ note.acknowledger.name }}</b>
                        </p>
                    </div>
                </div>

                <div class="mt-4 whitespace-pre-wrap text-sm text-on-surface">
                    {{ note.content }}
                </div>
            </AppCard>

            <AppCard v-if="isAcknowledged && note.acknowledgment_note" class="border border-success/40 bg-success/10 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-success">{{ t('Tanggapan Manajemen') }}</p>
                <p class="mt-2 whitespace-pre-wrap text-sm text-on-surface">{{ note.acknowledgment_note }}</p>
            </AppCard>

            <AppCard v-if="can.acknowledge && isSubmitted" class="p-5">
                <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                    {{ t('Tandai telah disetujui manajemen') }}
                </h2>

                <div v-if="!showAckForm" class="mt-3 flex justify-end gap-2">
                    <AppButton type="button" variant="secondary" @click="showAckForm = true">
                        {{ t('Tandai Disetujui') }}
                    </AppButton>
                </div>

                <form v-else class="mt-3 space-y-3" @submit.prevent="acknowledge">
                    <AppTextarea
                        v-model="ackForm.acknowledgment_note"
                        :label="t('Catatan Tanggapan (opsional)')"
                        rows="4"
                        :placeholder="t('Tulis tanggapan...')"
                    />
                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="inline-flex h-11 items-center rounded-xl border border-outline-variant px-4 text-sm font-semibold text-primary hover:bg-surface-container-low"
                            @click="showAckForm = false"
                        >
                            {{ t('Batal') }}
                        </button>
                        <AppButton type="submit" icon="check_circle" :loading="ackForm.processing">
                            {{ t('Simpan Persetujuan') }}
                        </AppButton>
                    </div>
                </form>
            </AppCard>

            <div v-if="can.delete && isDraft" class="flex justify-end">
                <button
                    type="button"
                    class="text-xs text-error hover:underline"
                    @click="remove"
                >
                    {{ t('Hapus catatan') }}
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
