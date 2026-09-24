<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppSelect from '../../../Components/SmartSelect.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

interface NoteRow {
    row_id: number;
    public_id: string;
    period_year: number;
    period_month: number | null;
    period_label: string;
    category: string;
    category_label: string;
    subject: string;
    content_preview: string;
    status: string;
    status_label: string;
    submitted_at: string | null;
    acknowledged_at: string | null;
    supervisor: { row_id: number; name: string } | null;
    acknowledger: { row_id: number; name: string } | null;
}

interface ApprovedReport {
    row_id: number;
    name: string;
    kind: string;
    period_label: string;
    approved_at: string | null;
}

interface CategoryLabels {
    [key: string]: string;
}

interface Filters {
    year: number;
    month: number | string;
    category: string;
    status: string;
}

const props = defineProps<{
    period: { year: number; month: number | null; period_label: string };
    identity: { legal_name: string; short_name: string | null };
    notes: NoteRow[];
    paginator: { page: number; per_page: number; total: number; last_page: number };
    filters: Filters;
    categories: CategoryLabels;
    monthLabels: Record<string, string>;
    statusOptions: Array<{ value: string; label: string }>;
    reports: ApprovedReport[];
    error?: string;
}>();

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(toMonthValue(props.filters.year, props.filters.month));
const selectedCategory = ref<string>(props.filters.category === 'all' || props.filters.category === '' ? '' : props.filters.category);
const selectedStatus = ref<string>(props.filters.status === 'all' || props.filters.status === '' ? '' : props.filters.status);

const categoryOptions = computed(() => [
    { value: '', label: 'Semua Kategori' },
    ...Object.entries(props.categories).map(([value, label]) => ({ value, label })),
]);

const statusOptions = computed(() => props.statusOptions);

const totalCount = computed<number>(() => props.paginator?.total ?? props.notes.length);

function toMonthValue(year: number, month: number | string | null | undefined): string {
    if (month === null || month === undefined || month === '' || month === 'all') return '';
    const m = Number(month);
    if (m < 1 || m > 12) return '';
    return `${year}-${String(m).padStart(2, '0')}`;
}

function applyFilters(): void {
    const params: Record<string, string | number> = {
        year: selectedYear.value,
    };
    if (selectedMonth.value) {
        params.month = Number(selectedMonth.value.slice(5, 7));
    } else {
        params.month = 'all';
    }
    if (selectedCategory.value) params.category = selectedCategory.value;
    if (selectedStatus.value) params.status = selectedStatus.value;
    router.get('/supervisor/notes', params, { preserveScroll: true, replace: true });
}

function statusTone(status: string): string {
    if (status === 'acknowledged') return 'success';
    if (status === 'submitted') return 'info-soft';
    return 'neutral';
}

function showHref(n: NoteRow): string {
    return `/supervisor/notes/${n.row_id}`;
}

function editHref(n: NoteRow): string {
    return `/supervisor/notes/${n.row_id}/edit`;
}

function formatDateTime(value: string | null): string {
    if (!value) return '—';
    try {
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
    } catch {
        return value;
    }
}
</script>

<template>
    <Head title="Catatan Pengawas" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">
                        Pengawas
                    </p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Catatan Pengawas</h1>
                    <p class="text-sm text-on-surface-variant">
                        {{ period.period_label }} · Catatan & rekomendasi kinerja lembaga
                    </p>
                </div>
                <AppButton
                    tag="a"
                    href="/supervisor/notes/create"
                    icon="add"
                    size="compact"
                >
                    Catatan Baru
                </AppButton>
            </div>

            <AppCard v-if="error" class="border border-error/30 bg-error-container/40 p-4">
                <p class="text-sm text-error">
                    <span class="font-bold">Gagal memuat data:</span> {{ error }}
                </p>
            </AppCard>

            <AppCard class="p-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <AppDatePicker v-model="selectedYear" label="Tahun" mode="year" required />
                    <AppDatePicker
                        v-model="selectedMonth"
                        label="Bulan"
                        mode="month"
                        clearable
                        placeholder="Semua bulan"
                    />
                    <AppSelect
                        v-model="selectedCategory"
                        label="Kategori"
                        :options="categoryOptions"
                    />
                    <AppSelect
                        v-model="selectedStatus"
                        label="Status"
                        :options="statusOptions"
                    />
                    <div class="flex items-end">
                        <AppButton type="button" class="w-full" icon="filter_alt" @click="applyFilters">
                            Terapkan
                        </AppButton>
                    </div>
                </div>
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="flex items-center justify-between border-b border-outline-variant/40 bg-surface-container-low px-4 py-3 text-sm">
                    <div>
                        <p class="font-bold text-primary">{{ totalCount }} catatan</p>
                        <p class="text-xs text-on-surface-variant">
                            Daftar catatan pengawas sesuai filter
                        </p>
                    </div>
                </div>

                <div v-if="notes.length === 0">
                    <AppEmptyState
                        title="Belum ada catatan"
                        description="Catatan pengawas akan tampil di sini setelah dibuat."
                        icon="rate_review"
                    >
                        <AppButton tag="a" href="/supervisor/notes/create" icon="add" variant="primary">
                            Buat Catatan Pertama
                        </AppButton>
                    </AppEmptyState>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left w-12">#</th>
                                <th class="px-3 py-2 text-left">Periode</th>
                                <th class="px-3 py-2 text-left">Kategori</th>
                                <th class="px-3 py-2 text-left">Judul</th>
                                <th class="px-3 py-2 text-left w-40">Status</th>
                                <th class="px-3 py-2 text-left w-44">Pengawas</th>
                                <th class="px-3 py-2 text-left w-44">Tgl. Submit</th>
                                <th class="px-3 py-2 text-right w-32">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(n, idx) in notes"
                                :key="n.row_id"
                                class="border-t border-outline-variant/30 align-top hover:bg-surface-container-lowest"
                            >
                                <td class="px-3 py-2 tabular-nums">{{ idx + 1 }}</td>
                                <td class="px-3 py-2">
                                    <span class="font-semibold">{{ n.period_label }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <AppBadge tone="primary-soft">{{ n.category_label }}</AppBadge>
                                </td>
                                <td class="px-3 py-2">
                                    <a
                                        :href="showHref(n)"
                                        class="font-medium text-primary hover:underline"
                                    >
                                        {{ n.subject }}
                                    </a>
                                    <p class="mt-0.5 text-xs text-on-surface-variant">
                                        {{ n.content_preview }}
                                    </p>
                                </td>
                                <td class="px-3 py-2">
                                    <AppBadge :tone="statusTone(n.status)">
                                        {{ n.status_label }}
                                    </AppBadge>
                                    <p
                                        v-if="n.acknowledged_at"
                                        class="mt-1 text-[11px] text-on-surface-variant"
                                    >
                                        ACK: {{ formatDateTime(n.acknowledged_at) }}
                                    </p>
                                </td>
                                <td class="px-3 py-2">{{ n.supervisor?.name ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs">{{ formatDateTime(n.submitted_at) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-1.5">
                                        <a
                                            :href="showHref(n)"
                                            class="rounded-md border border-outline-variant px-2 py-1 text-xs hover:bg-surface-container-low"
                                        >
                                            Lihat
                                        </a>
                                        <a
                                            v-if="n.status === 'draft'"
                                            :href="editHref(n)"
                                            class="rounded-md border border-outline-variant px-2 py-1 text-xs hover:bg-surface-container-low"
                                        >
                                            Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="paginator && paginator.last_page > 1"
                    class="flex items-center justify-between border-t border-outline-variant/40 px-4 py-3 text-xs"
                >
                    <p class="text-on-surface-variant">
                        Halaman {{ paginator.page }} dari {{ paginator.last_page }}
                        ({{ paginator.total }} catatan)
                    </p>
                    <div class="flex gap-2">
                        <button
                            v-if="paginator.page > 1"
                            type="button"
                            class="rounded-md border border-outline-variant px-3 py-1 text-xs font-semibold hover:bg-surface-container-low"
                            @click="router.get('/supervisor/notes', { ...filters, month: filters.month ?? 'all', page: paginator.page - 1 }, { preserveScroll: true })"
                        >
                            Sebelumnya
                        </button>
                        <button
                            v-if="paginator.page < paginator.last_page"
                            type="button"
                            class="rounded-md border border-outline-variant px-3 py-1 text-xs font-semibold hover:bg-surface-container-low"
                            @click="router.get('/supervisor/notes', { ...filters, month: filters.month ?? 'all', page: paginator.page + 1 }, { preserveScroll: true })"
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            </AppCard>

            <AppCard class="p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-on-surface-variant">
                        Laporan disetujui pengawas
                    </h2>
                    <span class="text-xs text-on-surface-variant">{{ reports.length }} laporan</span>
                </div>

                <div v-if="reports.length === 0" class="rounded-lg border border-dashed border-outline-variant p-4 text-center text-sm text-on-surface-variant">
                    Belum ada laporan yang ditandai disetujui pengawas.
                </div>

                <ul v-else class="divide-y divide-outline-variant/30">
                    <li
                        v-for="r in reports"
                        :key="r.row_id"
                        class="flex items-center justify-between py-2 text-sm"
                    >
                        <div>
                            <p class="font-medium">{{ r.name }}</p>
                            <p class="text-xs text-on-surface-variant">
                                {{ r.kind }} · {{ r.period_label }}
                            </p>
                        </div>
                        <p class="text-xs text-on-surface-variant">
                            {{ formatDateTime(r.approved_at) }}
                        </p>
                    </li>
                </ul>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
