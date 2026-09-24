<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppSelect from '../../../Components/SmartSelect.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

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
    note: NotePayload | null;
    identity: { legal_name: string; short_name: string | null };
    categories: Record<string, string>;
    monthLabels: Record<string, string>;
    can: CanBag;
    mode: 'create' | 'edit';
    filters?: { year: number; month: number };
}>();

const page = usePage();
const errors = computed<Record<string, string>>(() => (page.props.errors as Record<string, string>) ?? {});

const form = useForm({
    period_year: props.note?.period_year ?? props.filters?.year ?? new Date().getFullYear(),
    period_month: props.note?.period_month ?? props.filters?.month ?? new Date().getMonth() + 1,
    category: props.note?.category ?? 'keuangan',
    subject: props.note?.subject ?? '',
    content: props.note?.content ?? '',
});

const selectedYear = computed<string>({
    get: () => String(form.period_year),
    set: (v: string) => {
        form.period_year = Number(v);
    },
});

const selectedMonth = computed<string>({
    get: () => {
        const m = form.period_month;
        if (!m) return '';
        return `${form.period_year}-${String(m).padStart(2, '0')}`;
    },
    set: (v: string) => {
        if (!v) {
            form.period_month = null;
            return;
        }
        const parts = v.split('-');
        const m = Number(parts[1]);
        form.period_month = m;
    },
});

const monthOptions = computed(() => {
    return Array.from({ length: 12 }, (_, i) => {
        const m = i + 1;
        return {
            value: `${form.period_year}-${String(m).padStart(2, '0')}`,
            label: props.monthLabels[m] ?? `Bulan ${m}`,
        };
    });
});

const categoryOptions = computed(() =>
    Object.entries(props.categories).map(([value, label]) => ({ value, label })),
);

const isEdit = computed<boolean>(() => props.mode === 'edit');
const canSubmit = computed<boolean>(() => props.can.submit);
const canDelete = computed<boolean>(() => props.can.delete);

function submit(): void {
    if (isEdit.value && props.note) {
        form.put(`/supervisor/notes/${props.note.row_id}`, {
            preserveScroll: true,
        });
    } else {
        form.post('/supervisor/notes', {
            preserveScroll: true,
        });
    }
}

function submitForReview(): void {
    if (!props.note) return;
    router.post(`/supervisor/notes/${props.note.row_id}/submit`, {}, { preserveScroll: true });
}

function remove(): void {
    if (!props.note) return;
    if (typeof window !== 'undefined' && !window.confirm('Hapus catatan ini? Tindakan tidak dapat dibatalkan.')) {
        return;
    }
    router.delete(`/supervisor/notes/${props.note.row_id}`, { preserveScroll: true });
}

function backHref(): string {
    return props.note ? `/supervisor/notes/${props.note.row_id}` : '/supervisor/notes';
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Catatan Pengawas' : 'Catatan Pengawas Baru'" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pengawas
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">
                        {{ isEdit ? 'Edit Catatan Pengawas' : 'Catatan Pengawas Baru' }}
                    </h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ identity.legal_name }} · {{ isEdit && note ? note.period_label : 'Catatan & rekomendasi kinerja lembaga' }}
                    </p>
                </div>
                <a
                    :href="backHref()"
                    class="inline-flex h-10 items-center rounded-xl border border-outline-variant px-4 text-sm font-semibold text-primary hover:bg-surface-container-low"
                >
                    Kembali
                </a>
            </div>

            <AppCard v-if="isEdit && note" class="p-4">
                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <p class="text-on-surface-variant">Status saat ini:</p>
                    <AppBadge
                        :tone="note.status === 'acknowledged' ? 'success' : note.status === 'submitted' ? 'info-soft' : 'neutral'"
                    >
                        {{ note.status_label }}
                    </AppBadge>
                    <p v-if="note.submitted_at" class="text-xs text-on-surface-variant">
                        Disubmit: {{ new Date(note.submitted_at).toLocaleString('id-ID') }}
                    </p>
                    <p v-if="note.acknowledged_at" class="text-xs text-on-surface-variant">
                        ACK: {{ new Date(note.acknowledged_at).toLocaleString('id-ID') }}
                    </p>
                </div>
            </AppCard>

            <AppCard class="p-5">
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <AppDatePicker v-model="selectedYear" label="Tahun Periode" mode="year" required />
                        <AppSelect
                            v-model="selectedMonth"
                            label="Bulan"
                            :options="monthOptions"
                            placeholder="Tahunan (semua bulan)"
                            clearable
                        />
                        <AppSelect
                            v-model="form.category"
                            label="Kategori"
                            :options="categoryOptions"
                            required
                            :error="errors.category"
                        />
                    </div>

                    <AppInput
                        v-model="form.subject"
                        label="Judul Catatan"
                        placeholder="Contoh: Pertumbuhan piutang perlu perhatian"
                        required
                        :error="errors.subject"
                    />

                    <AppTextarea
                        v-model="form.content"
                        label="Isi Catatan"
                        rows="10"
                        placeholder="Tuliskan catatan, observasi, atau rekomendasi pengawas..."
                        required
                        :error="errors.content"
                    />

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <a
                            :href="backHref()"
                            class="inline-flex h-11 items-center rounded-xl border border-outline-variant px-4 text-sm font-semibold text-primary hover:bg-surface-container-low"
                        >
                            Batal
                        </a>
                        <AppButton
                            v-if="canDelete && isEdit"
                            type="button"
                            variant="danger"
                            icon="delete"
                            @click="remove"
                        >
                            Hapus
                        </AppButton>
                        <AppButton
                            v-if="canSubmit && isEdit"
                            type="button"
                            variant="secondary"
                            icon="send"
                            @click="submitForReview"
                        >
                            Simpan & Submit
                        </AppButton>
                        <AppButton
                            type="submit"
                            icon="save"
                            :loading="form.processing"
                        >
                            {{ isEdit ? 'Simpan Perubahan' : 'Simpan Catatan' }}
                        </AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
