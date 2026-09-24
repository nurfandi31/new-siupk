<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppButton from './AppButton.vue';
import AppEmptyState from './AppEmptyState.vue';
import AppIcon from './AppIcon.vue';
import AppInput from './AppInput.vue';
import SmartSelect from './SmartSelect.vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    columns: { type: Array, required: true },
    pagination: { type: Object, required: true },
    url: { type: String, required: true },
    search: { type: String, default: '' },
    searchPlaceholder: { type: String, default: 'Cari data' },
    searchLabel: { type: String, default: 'Pencarian' },
    perPageOptions: { type: Array, default: () => [15, 30, 50, 100] },
    perPage: { type: [Number, String], default: 15 },
    sort: { type: String, default: '' },
    direction: { type: String, default: 'asc' },
    emptyTitle: { type: String, default: 'Belum ada data' },
    emptyDescription: { type: String, default: 'Belum ada data untuk ditampilkan.' },
    dense: { type: Boolean, default: false },
    rowHref: { type: [Function, String], default: null },
});

const query = ref(props.search);
let timer;
const processing = computed(() => router.processing);
const currentPage = computed(() => Number(props.pagination.current_page || 1));
const lastPage = computed(() => Number(props.pagination.last_page || 1));
const pages = computed(() => {
    const start = Math.max(1, Math.min(currentPage.value - 2, lastPage.value - 4));
    const end = Math.min(lastPage.value, start + 4);
    return Array.from({ length: end - start + 1 }, (_, index) => start + index);
});
const perPageOptions = computed(() => props.perPageOptions.map((value) => ({ value: Number(value), label: String(value) })));

watch(() => props.search, (value) => { query.value = value; });

function visit(parameters = {}) {
    router.get(props.url, {
        search: query.value || undefined,
        per_page: Number(props.perPage),
        sort: props.sort || undefined,
        direction: props.direction || undefined,
        ...parameters,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function scheduleSearch() {
    clearTimeout(timer);
    timer = setTimeout(() => visit({ page: 1 }), 350);
}

function changePerPage(value) {
    visit({ per_page: Number(value), page: 1 });
}
function sortBy(column) {
    if (!column.sortable) return;
    const direction = props.sort === column.key && props.direction === 'asc' ? 'desc' : 'asc';
    visit({ sort: column.key, direction, page: 1 });
}
function goTo(page) { if (page >= 1 && page <= lastPage.value && page !== currentPage.value) visit({ page }); }
function resetSearch() { query.value = ''; visit({ search: undefined, page: 1 }); }

function resolveRowHref(row) {
    if (!props.rowHref) return null;
    if (typeof props.rowHref === 'function') return props.rowHref(row);
    // Dukung placeholder {key} atau {a.b.c} (nested access).
    // Untuk konsistensi, anggap placeholder sebagai nested path; user yang ingin
    // mengakses field dengan nama mengandung '.' harus menggunakan fungsi rowHref.
    return String(props.rowHref).replace(/\{([\w.]+)\}/g, (_, path) => {
        let cursor = row;
        for (const key of path.split('.')) {
            if (cursor == null) return '';
            cursor = cursor[key];
        }
        return cursor ?? '';
    });
}

function goToRow(row, event) {
    if (event) {
        // Abaikan klik yang berasal dari elemen interaktif (button, a, input, label, select, textarea)
        const interactive = event.target.closest('a, button, input, select, textarea, label, [role="button"], [data-row-stop]');
        if (interactive) return;
        // Abaikan jika user sedang menyeleksi teks
        if (window.getSelection && window.getSelection().toString().length > 0) return;
        // Abaikan klik dengan modifier (biasanya user ingin buka di tab baru)
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    }
    const href = resolveRowHref(row);
    if (!href) return;
    router.get(href, {}, { preserveState: true, preserveScroll: true });
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="space-y-3 px-3 py-3 sm:space-y-4 sm:px-4 sm:py-4">
        <div class="flex flex-col gap-2 inset-divider pb-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:pb-3">
            <div class="flex flex-wrap items-center gap-2"><SmartSelect id="smart-table-per-page" label="Per halaman" hide-label size="compact" :model-value="Number(perPage)" :options="perPageOptions" :disabled="processing" @update:model-value="changePerPage"/><slot name="toolbar" /></div>
            <div class="min-w-0 max-w-56 sm:ml-auto"><AppInput v-model="query" :label="searchLabel" hide-label icon="search" size="compact" :placeholder="searchPlaceholder" @input="scheduleSearch"><template v-if="query" #trailing><button type="button" class="rounded-full p-1 text-outline hover:bg-surface-container-low hover:text-primary" aria-label="Hapus pencarian" @click="resetSearch"><AppIcon name="close" class="text-base" /></button></template></AppInput></div>
        </div>
        <div class="relative overflow-x-auto">
            <div v-if="processing" class="absolute inset-0 z-10 flex items-start justify-center bg-surface-container-lowest/60 pt-16 text-sm text-primary">Memuat...</div>
            <table :class="['w-full text-left text-sm', dense && 'table-fixed']"><thead class="bg-surface-container-low text-sm"><tr><th v-for="column in columns" :key="column.key" :class="[dense ? 'px-2.5 py-2 sm:px-3 sm:py-2.5' : 'px-2.5 py-3 sm:px-4 sm:py-3.5', column.class, column.thClass]"><button v-if="column.sortable" type="button" class="inline-flex items-center gap-1 whitespace-nowrap font-bold text-primary" @click="sortBy(column)">{{ column.label }}<AppIcon :name="sort === column.key ? direction === 'asc' ? 'arrow_upward' : 'arrow_downward' : 'unfold_more'" class="text-lg text-outline" /></button><span v-else class="whitespace-nowrap">{{ column.label }}</span></th><th v-if="$slots.actions" :class="[dense ? 'px-2.5 py-2 sm:px-3 sm:py-2.5' : 'px-2.5 py-3 sm:px-4 sm:py-3.5', 'whitespace-nowrap text-right']">Aksi</th></tr></thead><tbody><tr v-for="(row, index) in rows" :key="row.row_id || row.id || index" :class="['border-t border-outline-variant', rowHref && 'cursor-pointer transition-colors hover:bg-surface-container-low/60 focus-within:bg-surface-container-low/60']" :tabindex="rowHref ? 0 : -1" :aria-label="rowHref ? `Buka detail baris ${index + 1}` : undefined" @click="rowHref && goToRow(row, $event)" @keydown.enter.prevent="rowHref && goToRow(row)" @keydown.space.prevent="rowHref && goToRow(row)"><td v-for="column in columns" :key="column.key" :class="[dense ? 'px-2.5 py-2 align-middle sm:px-3 sm:py-2.5' : 'px-2.5 py-3 align-middle sm:px-4 sm:py-3.5', column.class, column.tdClass]"><slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">{{ row[column.key] ?? '—' }}</slot></td><td v-if="$slots.actions" data-row-stop :class="[dense ? 'px-2.5 py-2 align-middle sm:px-3 sm:py-2.5' : 'px-2.5 py-3 align-middle sm:px-4 sm:py-3.5', 'whitespace-nowrap text-right']"><slot name="actions" :row="row" /></td></tr></tbody></table>
            <div v-if="!rows.length && !processing" class="p-6"><AppEmptyState icon="database" :title="emptyTitle" :description="emptyDescription" /></div>
        </div>
        <div class="flex flex-col gap-3 text-sm text-on-surface-variant sm:flex-row sm:items-center sm:justify-between sm:gap-4"><p>{{ pagination.from || 0 }}–{{ pagination.to || 0 }} / {{ pagination.total || 0 }}</p><nav v-if="lastPage > 1" class="flex items-center gap-1" aria-label="Pagination"><AppButton variant="ghost" size="compact" :disabled="currentPage === 1 || processing" aria-label="Halaman sebelumnya" @click="goTo(currentPage - 1)"><AppIcon name="chevron_left" /></AppButton><AppButton v-for="page in pages" :key="page" size="compact" :variant="page === currentPage ? 'primary' : 'ghost'" :disabled="processing" @click="goTo(page)">{{ page }}</AppButton><AppButton variant="ghost" size="compact" :disabled="currentPage === lastPage || processing" aria-label="Halaman berikutnya" @click="goTo(currentPage + 1)"><AppIcon name="chevron_right" /></AppButton></nav></div>
    </div>
</template>
