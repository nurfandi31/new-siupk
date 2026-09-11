<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AppIcon from '../../../Components/AppIcon.vue';

const props = defineProps({
    tenants: { type: Array, required: true },
    runs: { type: Object, required: true },
    legacy_config: { type: Object, default: () => ({ host: '127.0.0.1', port: 3306, database: 'siupk' }) },
    discovered_suffixes: { type: Array, default: () => [] },
});

const selectedRun = ref(null);
const activeLogModal = ref(false);
const logTerminal = ref(null);
const isUserScrolledUp = ref(false);
let pollInterval = null;
let sseSource = null;

// Discovery loaded lazily via AJAX — querying MIN/MAX on every suffix in
// the remote legacy MySQL can take 60+ seconds, so the page renders
// immediately and only runs discovery when the user opens the form.
const discoveredSuffixes = ref(props.discovered_suffixes ?? []);
const legacyTenants = ref([]);
const isDiscovering = ref(false);
const isLoadingLegacyTenants = ref(false);
const discoveryError = ref(null);
const legacyTenantsError = ref(null);
const discoveryCount = ref(discoveredSuffixes.value.length);
const isExpertMode = ref(false);
const simpleFallbackMode = ref('auto');

const runDiscovery = async (force = false) => {
    if (isDiscovering.value) return;
    isDiscovering.value = true;
    discoveryError.value = null;
    try {
        const url = '/admin/migration/discover' + (force ? '?refresh=1' : '');
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) {
            discoveryError.value = data.error || `HTTP ${res.status}`;
            return;
        }
        discoveredSuffixes.value = Array.isArray(data.suffixes) ? data.suffixes : [];
        discoveryCount.value = data.count ?? discoveredSuffixes.value.length;
    } catch (e) {
        discoveryError.value = e?.message ?? 'Gagal memanggil endpoint discover.';
    } finally {
        isDiscovering.value = false;
    }
};

const runLegacyTenantLoad = async (force = false) => {
    if (isLoadingLegacyTenants.value) return;
    isLoadingLegacyTenants.value = true;
    legacyTenantsError.value = null;
    try {
        const url = '/admin/migration/legacy-tenants' + (force ? '?refresh=1' : '');
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) {
            legacyTenantsError.value = data.error || `HTTP ${res.status}`;
            return;
        }
        legacyTenants.value = Array.isArray(data.legacy_tenants) ? data.legacy_tenants : [];
    } catch (e) {
        legacyTenantsError.value = e?.message ?? 'Gagal memuat daftar tenant legacy.';
    } finally {
        isLoadingLegacyTenants.value = false;
    }
};

onMounted(() => {
    runLegacyTenantLoad(false);
    runDiscovery(false);
});

const tenantOptions = computed(() =>
    props.tenants.map(t => ({
        value: t.row_id,
        label: t.name,
        subtitle: `Code: ${t.code} • Tenant ID #${t.row_id}`,
        badge: `ID #${t.row_id}`,
    }))
);

const selectedLegacyTenant = computed(() =>
    legacyTenants.value.find(item => String(item.legacy_id) === String(form.legacy_id)) ?? null,
);

const legacyTenantOptions = computed(() =>
    legacyTenants.value.map(item => {
        const tenant = item.next_tenant;
        return {
            value: item.legacy_id,
            label: `${item.legacy_name} (${item.legacy_code})`,
            subtitle: tenant
                ? `Target: ${tenant.name} (${tenant.code})`
                : 'Belum ada tenant Next — tenant baru dapat dibuat otomatis.',
            badge: tenant ? tenant.name : 'Belum ada tenant Next',
        };
    }),
);

const manualFallbackTenantOptions = computed(() =>
    props.tenants.map(t => ({
        value: t.row_id,
        label: t.name,
        subtitle: `Code: ${t.code} • District: ${t.district_code ?? '-'}`,
        badge: `ID #${t.row_id}`,
    })),
);

const suffixOptions = computed(() => {
    if (!discoveredSuffixes.value || discoveredSuffixes.value.length === 0) {
        return [{ value: '1', label: 'Suffix 1', subtitle: 'Default manual input (transaksi_1 / saldo_1)' }];
    }
    return discoveredSuffixes.value.map(s => {
        const countText = s.transaksi_count !== null
            ? `${s.transaksi_count.toLocaleString('id-ID')} transaksi`
            : 'Tabel Ditemukan';
        const dateRange = s.min_date ? ` (${s.min_date} s/d ${s.max_date})` : '';
        return {
            value: String(s.suffix),
            label: `Suffix ${s.suffix} — ${s.transaksi_table}`,
            subtitle: `${countText}${dateRange}`,
            badge: s.transaksi_count !== null ? `${s.transaksi_count.toLocaleString('id-ID')} Trx` : null,
        };
    });
});

const form = useForm({
    legacy_id: '',
    tenant_id: '',
    suffix: '',
    auto_provision: true,
    is_dry_run: false,
    run_immediately: false,
    chunk: 500,
    from_year: '2018',
    to_year: String(new Date().getFullYear()),
    skip_fiscal: false,
    skip_coa: false,
    skip_accounting: false,
    skip_membership: false,
    skip_lending: false,
    skip_payment_progress: false,
    skip_reconcile: false,
    skip_sequences: false,
    continue_on_error: false,
    no_fail_fast: false,
});

const submitCutover = () => {
    if (!isExpertMode.value) {
        form.suffix = '';
        form.tenant_id = simpleFallbackMode.value === 'manual' ? form.tenant_id : '';
        form.auto_provision = simpleFallbackMode.value === 'auto';
    } else {
        form.legacy_id = '';
        form.auto_provision = false;
    }
    form.post('/admin/migrations', {
        preserveScroll: true,
        onSuccess: () => {
            if (props.runs.data && props.runs.data.length > 0) {
                openLogModal(props.runs.data[0]);
            }
        },
    });
};

const resetFormForMode = (mode) => {
    if (mode === 'expert') {
        form.legacy_id = '';
        form.auto_provision = false;
        if (!form.tenant_id) form.tenant_id = props.tenants[0]?.row_id ?? '';
        if (!form.suffix) form.suffix = '1';
        return;
    }

    form.suffix = '';
    form.auto_provision = simpleFallbackMode.value === 'auto';
    if (simpleFallbackMode.value === 'manual') {
        if (!form.tenant_id) form.tenant_id = props.tenants[0]?.row_id ?? '';
        return;
    }
    form.tenant_id = selectedLegacyTenant.value?.next_tenant?.row_id ?? '';
};

watch(selectedLegacyTenant, (item) => {
    if (isExpertMode.value) return;
    form.tenant_id = item?.next_tenant?.row_id ?? '';
    form.auto_provision = simpleFallbackMode.value === 'auto';
});

watch(simpleFallbackMode, () => resetFormForMode('simple'));
watch(isExpertMode, () => resetFormForMode(isExpertMode.value ? 'expert' : 'simple'));

const onLogScroll = () => {
    if (!logTerminal.value) return;
    const { scrollTop, scrollHeight, clientHeight } = logTerminal.value;
    isUserScrolledUp.value = (scrollHeight - scrollTop - clientHeight > 30);
};

const scrollToBottom = () => {
    if (logTerminal.value) {
        logTerminal.value.scrollTop = logTerminal.value.scrollHeight;
        isUserScrolledUp.value = false;
    }
};

watch(() => selectedRun.value?.output_log, () => {
    nextTick(() => {
        if (!isUserScrolledUp.value) {
            scrollToBottom();
        }
    });
});

watch(activeLogModal, (isOpen) => {
    if (isOpen) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
    stopSseOrPolling();
});

const openLogModal = (run) => {
    selectedRun.value = run;
    activeLogModal.value = true;
    isUserScrolledUp.value = false;
    startSseOrPolling(run.id);
    nextTick(() => {
        scrollToBottom();
    });
};

const closeLogModal = () => {
    activeLogModal.value = false;
    stopSseOrPolling();
};

const retryRun = (run) => {
    const opts = run.options || {};
    form.tenant_id = run.tenant_id;
    form.suffix = run.suffix;
    form.is_dry_run = Boolean(run.is_dry_run);
    form.run_immediately = true;
    form.chunk = opts.chunk ?? 500;
    form.from_year = String(opts.from_year ?? 2018);
    form.to_year = String(opts.to_year ?? new Date().getFullYear());
    form.skip_fiscal = Boolean(opts.skip_fiscal);
    form.skip_coa = Boolean(opts.skip_coa);
    form.skip_accounting = Boolean(opts.skip_accounting);
    form.skip_membership = Boolean(opts.skip_membership);
    form.skip_lending = Boolean(opts.skip_lending);
    form.skip_payment_progress = Boolean(opts.skip_payment_progress);
    form.skip_reconcile = Boolean(opts.skip_reconcile);
    form.skip_sequences = Boolean(opts.skip_sequences);

    submitCutover();
};

const startSseOrPolling = (runId) => {
    stopSseOrPolling();

    if (selectedRun.value && (selectedRun.value.status === 'completed' || selectedRun.value.status === 'failed')) {
        fetchRunDetails(runId);
        return;
    }

    try {
        const streamUrl = `/admin/migrations/${runId}/stream`;
        sseSource = new EventSource(streamUrl);

        sseSource.addEventListener('update', (event) => {
            try {
                const data = JSON.parse(event.data);
                if (selectedRun.value) {
                    if (data.output_log !== undefined) selectedRun.value.output_log = data.output_log;
                    if (data.steps !== undefined) selectedRun.value.steps = data.steps;
                    if (data.status !== undefined) selectedRun.value.status = data.status;
                    if (data.error_message !== undefined) selectedRun.value.error_message = data.error_message;
                }
                if (data.status === 'completed' || data.status === 'failed') {
                    stopSseOrPolling();
                    router.reload({ only: ['runs'] });
                }
            } catch (err) {
                console.error('SSE parse error:', err);
                startPolling(runId);
            }
        });

        sseSource.onerror = (err) => {
            console.warn('SSE stream error, falling back to polling:', err);
            stopSseOrPolling();
            startPolling(runId);
        };
    } catch (e) {
        console.warn('EventSource not supported, using polling:', e);
        startPolling(runId);
    }
};

const startPolling = (runId) => {
    stopSseOrPolling();
    fetchRunDetails(runId);
    pollInterval = setInterval(() => {
        if (selectedRun.value && (selectedRun.value.status === 'running' || selectedRun.value.status === 'pending')) {
            fetchRunDetails(runId);
        } else {
            stopSseOrPolling();
        }
    }, 2500);
};

const stopSseOrPolling = () => {
    if (sseSource) {
        sseSource.close();
        sseSource = null;
    }
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const fetchRunDetails = async (runId) => {
    try {
        const response = await fetch(`/admin/migrations/${runId}`);
        if (response.ok) {
            const data = await response.json();
            selectedRun.value = data;
            if (data.status === 'completed' || data.status === 'failed') {
                router.reload({ only: ['runs'] });
            }
        }
    } catch (e) {
        console.error('Failed to poll migration run log:', e);
    }
};

const getStatusVariant = (status) => {
    switch (status) {
        case 'completed': return 'success';
        case 'running': return 'info';
        case 'failed': return 'error';
        default: return 'neutral';
    }
};

const getStepStatusVariant = (status) => {
    switch (status) {
        case 'ok': return 'success';
        case 'running': return 'warning';
        case 'failed': return 'error';
        case 'skipped': return 'neutral';
        default: return 'neutral';
    }
};
</script>

<template>
    <Head title="Migrasi Data Tenant (Cutover)" />

    <AdminLayout>
        <div class="space-y-6">
            <!-- Header section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-primary">Migrasi & Cutover Tenant</h1>
                    <p class="text-sm text-on-surface-variant">
                        Eksekusi migrasi data dari database MySQL legacy siupk (transaksi_*, saldo_*, dll) ke tenant Next platform.
                    </p>
                </div>
            </div>

            <!-- Form Eksekusi Cutover & Petunjuk -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left: Form Input Executions -->
                <div class="lg:col-span-2">
                    <AppCard>
                        <template #header>
                            <h2 class="text-lg font-bold text-primary">Form Eksekusi Migrasi Baru</h2>
                        </template>

                        <form @submit.prevent="submitCutover" class="space-y-5">
                            <div v-if="!isExpertMode" class="space-y-4">
                                <div>
                                    <SmartSelect
                                        v-model="form.legacy_id"
                                        label="Pilih Tenant Legacy"
                                        :options="legacyTenantOptions"
                                        :error="form.errors.legacy_id"
                                        :loading="isLoadingLegacyTenants"
                                        hint="Nama kecamatan dicocokkan dengan district_code tenant Next."
                                        searchable
                                        required
                                    />
                                    <p v-if="legacyTenantsError" class="mt-2 text-sm text-error">{{ legacyTenantsError }}</p>
                                </div>

                                <div v-if="selectedLegacyTenant" class="space-y-3 rounded-xl border border-outline-variant p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-primary">Target</p>
                                            <p v-if="selectedLegacyTenant.next_tenant" class="text-xs text-on-surface-variant">
                                                {{ selectedLegacyTenant.next_tenant.name }} ({{ selectedLegacyTenant.next_tenant.code }})
                                            </p>
                                            <p v-else class="text-xs text-on-surface-variant">
                                                Tenant Next belum ditemukan untuk kd_kec {{ selectedLegacyTenant.legacy_code }}.
                                            </p>
                                        </div>
                                        <AppBadge :tone="selectedLegacyTenant.next_tenant ? 'success' : 'warning'">
                                            {{ selectedLegacyTenant.next_tenant ? 'Tenant Next ketemu' : 'Belum ada tenant Next' }}
                                        </AppBadge>
                                    </div>

                                    <div v-if="!selectedLegacyTenant.next_tenant" class="grid gap-3 sm:grid-cols-2">
                                        <AppButton
                                            type="button"
                                            :variant="simpleFallbackMode === 'auto' ? 'primary' : 'secondary'"
                                            size="compact"
                                            @click="simpleFallbackMode = 'auto'"
                                        >
                                            Buat tenant baru otomatis
                                        </AppButton>
                                        <AppButton
                                            type="button"
                                            :variant="simpleFallbackMode === 'manual' ? 'primary' : 'secondary'"
                                            size="compact"
                                            @click="simpleFallbackMode = 'manual'"
                                        >
                                            Pilih tenant Next manual
                                        </AppButton>
                                    </div>

                                    <SmartSelect
                                        v-if="!selectedLegacyTenant.next_tenant && simpleFallbackMode === 'manual'"
                                        v-model="form.tenant_id"
                                        label="Pilih Tenant Next"
                                        :options="manualFallbackTenantOptions"
                                        :error="form.errors.tenant_id"
                                        searchable
                                        required
                                    />
                                </div>

                                <div class="rounded-xl border border-outline-variant p-4">
                                    <AppSwitch
                                        v-model="form.is_dry_run"
                                        label="Mode Dry-Run (Simulasi / Uji Coba)"
                                        description="Menjalankan simulasi validasi tanpa menyimpan perubahan ke database utama."
                                        icon="science"
                                    />
                                </div>
                            </div>

                            <div v-else class="space-y-5">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <SmartSelect
                                            v-model="form.tenant_id"
                                            label="Pilih Tenant Target"
                                            :options="tenantOptions"
                                            :error="form.errors.tenant_id"
                                            searchable
                                            required
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <SmartSelect
                                            v-if="discoveredSuffixes.length > 0"
                                            v-model="form.suffix"
                                            label="ID Lokasi (Suffix Terdeteksi)"
                                            :options="suffixOptions"
                                            :error="form.errors.suffix"
                                            searchable
                                            required
                                        />
                                        <AppInput
                                            v-else
                                            v-model="form.suffix"
                                            label="ID Lokasi (Suffix Legacy DB)"
                                            placeholder="misal: 1 atau 76"
                                            hint="Akhiran tabel transaksi_* di database legacy (contoh: 1 untuk transaksi_1)"
                                            required
                                            :error="form.errors.suffix"
                                        />
                                        <div class="flex items-center justify-between gap-2 text-xs">
                                            <span class="text-on-surface-variant">
                                                <template v-if="isDiscovering">Memindai database legacy… ({{ Math.round(70) }}s)</template>
                                                <template v-else-if="discoveryError">
                                                    <span class="text-error">Gagal memindai: {{ discoveryError }}</span>
                                                </template>
                                                <template v-else-if="discoveredSuffixes.length > 0">
                                                    {{ discoveryCount }} suffix terdeteksi.
                                                </template>
                                                <template v-else>
                                                    Belum ada suffix terdeteksi — klik "Pindai Ulang DB Legacy".
                                                </template>
                                            </span>
                                            <AppButton
                                                type="button"
                                                variant="ghost"
                                                size="compact"
                                                icon="search"
                                                :disabled="isDiscovering"
                                                @click="runDiscovery(true)"
                                            >
                                                Pindai Ulang DB Legacy
                                            </AppButton>
                                        </div>
                                    </div>
                                </div>

                                <div class="space-y-3 rounded-xl border border-outline-variant p-4">
                                    <AppSwitch
                                        v-model="form.run_immediately"
                                        label="Eksekusi Langsung (Synchronous Execution)"
                                        description="Jalankan langsung di server tanpa menunggu antrean background worker."
                                        icon="bolt"
                                    />
                                </div>

                                <div class="space-y-4 rounded-xl border border-outline-variant p-4">
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                        <div>
                                            <AppInput v-model="form.chunk" label="Chunk Size" type="number" min="10" max="5000" />
                                        </div>
                                        <div>
                                            <AppDatePicker v-model="form.from_year" label="Tahun Fiskal Dari" mode="year" placeholder="Pilih Tahun" />
                                        </div>
                                        <div>
                                            <AppDatePicker v-model="form.to_year" label="Tahun Fiskal Sampai" mode="year" placeholder="Pilih Tahun" />
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <span class="block text-xs font-bold uppercase tracking-wider text-primary">Lompati Step (Optional Skipping):</span>
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <AppSwitch v-model="form.skip_fiscal" label="Lompati Periode Fiskal" description="Skip Fiscal Periods" />
                                            <AppSwitch v-model="form.skip_coa" label="Lompati Bagan Akun (COA)" description="Skip COA Import" />
                                            <AppSwitch v-model="form.skip_accounting" label="Lompati Jurnal Akuntansi" description="Skip Accounting Jurnal" />
                                            <AppSwitch v-model="form.skip_membership" label="Lompati Keanggotaan" description="Skip Keanggotaan" />
                                            <AppSwitch v-model="form.skip_lending" label="Lompati Data Pinjaman" description="Skip Pinjaman" />
                                            <AppSwitch v-model="form.skip_payment_progress" label="Lompati Progress Angsuran" description="Skip Progress Angsuran" />
                                            <AppSwitch v-model="form.skip_reconcile" label="Lompati Rekonsiliasi" description="Skip Rekonsiliasi" />
                                            <AppSwitch v-model="form.skip_sequences" label="Lompati Sequences" description="Skip Sequences" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end pt-2">
                                <AppButton type="submit" variant="primary" icon="play_arrow" :disabled="form.processing">
                                    <span v-if="form.processing">Sedang Memproses...</span>
                                    <span v-else>Jalankan Migrasi Data</span>
                                </AppButton>
                            </div>

                            <div class="flex justify-center border-t border-outline-variant pt-3">
                                <AppButton
                                    type="button"
                                    variant="ghost"
                                    size="compact"
                                    :icon="isExpertMode ? 'expand_less' : 'expand_more'"
                                    @click="isExpertMode = !isExpertMode"
                                >
                                    Mode Expert (lanjutan)
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>
                </div>

                <!-- Right: Information Box -->
                <div>
                    <AppCard>
                        <template #header>
                            <h2 class="text-lg font-bold text-primary">Petunjuk Alur Migrasi</h2>
                        </template>
                        <div class="space-y-3 text-xs text-on-surface-variant leading-relaxed">
                            <div class="rounded-lg border border-outline-variant bg-surface-container-low p-3 space-y-1">
                                <span class="block text-xs font-bold text-primary">Koneksi Database Legacy Aktif:</span>
                                <p>Host: <code class="font-mono font-bold text-primary">{{ props.legacy_config?.host || '127.0.0.1' }}:{{ props.legacy_config?.port || 3306 }}</code></p>
                                <p>Database: <code class="font-mono font-bold text-primary">{{ props.legacy_config?.database || 'siupk' }}</code></p>
                            </div>

                            <div class="flex items-start space-x-2">
                                <span class="shrink-0 font-bold text-secondary">1.</span>
                                <p>Sistem membaca database MySQL legacy <code class="rounded bg-surface-container px-1 py-0.5 font-mono text-[11px]">{{ props.legacy_config?.database || 'siupk' }}</code> yang telah dikonfigurasi di file <code class="rounded bg-surface-container px-1 py-0.5 font-mono text-[11px] font-mono text-[11px] font-mono text-[11px] font-mono text-[11px] font-mono text-[11px]">.env</code>.</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="shrink-0 font-bold text-secondary">2.</span>
                                <p>Tabel legacy dibedakan oleh akhiran angka (Suffix ID), seperti <code class="rounded bg-surface-container px-1 py-0.5 font-mono text-[11px]">transaksi_1</code> atau <code class="rounded bg-surface-container px-1 py-0.5 font-mono text-[11px]">saldo_1</code>.</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="shrink-0 font-bold text-secondary">3.</span>
                                <p>Pilihlah <strong>Suffix Terdeteksi</strong> dari daftar dropdown. Sistem akan otomatis memindai tabel yang tersedia.</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="shrink-0 font-bold text-secondary">4.</span>
                                <p>Disarankan melakukan <strong>Mode Dry-Run</strong> terlebih dahulu untuk mensimulasikan validasi data tanpa mengubah database utama.</p>
                            </div>
                        </div>
                    </AppCard>
                </div>
            </div>

            <!-- Tabel Riwayat Executions -->
            <AppCard>
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-primary">Riwayat Eksekusi Migrasi (Cutover Runs)</h2>
                        <AppButton variant="ghost" size="compact" icon="refresh" @click="router.reload({ only: ['runs'] })">
                            Refresh Data
                        </AppButton>
                    </div>
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 font-bold">ID Run</th>
                                <th class="px-4 py-3 font-bold">Tenant Target</th>
                                <th class="px-4 py-3 font-bold">Suffix</th>
                                <th class="px-4 py-3 font-bold">Mode</th>
                                <th class="px-4 py-3 font-bold">Status</th>
                                <th class="px-4 py-3 font-bold">Waktu Mulai</th>
                                <th class="px-4 py-3 text-right font-bold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="run in runs.data" :key="run.id" class="transition hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-mono font-bold text-primary">#{{ run.id }}</td>
                                <td class="px-4 py-3 font-medium text-on-surface">{{ run.tenant_name }}</td>
                                <td class="px-4 py-3 font-mono text-on-surface-variant">
                                    <div class="space-y-1">
                                        <span>{{ run.suffix }}</span>
                                        <span v-if="run.options?.legacy_name" class="block">
                                            <AppBadge tone="neutral" class="text-[10px]">Legacy: {{ run.options.legacy_name }}</AppBadge>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <AppBadge :tone="run.is_dry_run ? 'neutral' : 'warning'">
                                        {{ run.is_dry_run ? 'Dry Run' : 'Live Cutover' }}
                                    </AppBadge>
                                </td>
                                <td class="px-4 py-3">
                                    <AppBadge :tone="getStatusVariant(run.status)">
                                        <span class="capitalize">{{ run.status }}</span>
                                    </AppBadge>
                                </td>
                                <td class="px-4 py-3 text-xs text-on-surface-variant">
                                    {{ run.started_at ? new Date(run.started_at).toLocaleString('id-ID') : 'Belum dimulai' }}
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <div class="flex items-center justify-end gap-2">
                                        <AppButton v-if="run.status === 'failed'" variant="warning" size="compact" icon="refresh" @click="retryRun(run)">
                                            Coba Ulangi (Retry)
                                        </AppButton>
                                        <AppButton variant="secondary" size="compact" icon="terminal" @click="openLogModal(run)">
                                            Detail & Log Output
                                        </AppButton>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="runs.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-on-surface-variant">
                                    Belum ada riwayat proses migrasi yang dijalankan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>

        <!-- Modal Detail Output Log & Live Step Progress -->
        <Teleport to="body">
            <div v-if="activeLogModal && selectedRun" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm">
                <div class="w-full max-w-4xl overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest shadow-2xl">
                    <div class="flex items-center justify-between border-b border-outline-variant p-4">
                        <div>
                            <h3 class="text-lg font-bold text-primary">
                                Monitoring Cutover Run #{{ selectedRun.id }} — {{ selectedRun.tenant_name }}
                            </h3>
                            <p class="text-xs text-on-surface-variant">Suffix: {{ selectedRun.suffix }} | Mode: {{ selectedRun.is_dry_run ? 'Dry Run' : 'Live' }}</p>
                        </div>
                        <AppButton variant="ghost" size="compact" icon="close" @click="closeLogModal" />
                    </div>

                    <div class="max-h-[80vh] overflow-y-auto space-y-4 p-4">
                        <!-- Step Progress Badges -->
                        <div v-if="selectedRun.steps && selectedRun.steps.length > 0" class="space-y-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary">Progress Tahapan Migrasi:</span>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <div v-for="step in selectedRun.steps" :key="step.name" class="rounded-lg border border-outline-variant p-2 text-xs">
                                    <div class="mb-1 flex items-center justify-between">
                                        <span class="truncate font-medium text-primary" :title="step.label">{{ step.name }}</span>
                                        <AppBadge :tone="getStepStatusVariant(step.status)" class="text-[10px] uppercase">
                                            {{ step.status }}
                                        </AppBadge>
                                    </div>
                                    <p class="truncate text-[11px] text-on-surface-variant">{{ step.label }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Output Console Terminal Window -->
                        <div class="relative space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-primary">Terminal Console Log Output:</span>
                                <span v-if="selectedRun.status === 'running'" class="animate-pulse text-xs font-medium text-tertiary">
                                    Live Polling Output...
                                </span>
                            </div>
                            <div class="relative">
                                <pre ref="logTerminal" class="h-80 w-full overflow-y-auto rounded-lg border border-outline bg-surface-container-lowest p-4 font-mono text-xs text-secondary shadow-inner whitespace-pre-wrap" @scroll="onLogScroll">{{ selectedRun.output_log || 'Menunggu keluaran log...' }}</pre>
                                <AppButton
                                    v-if="isUserScrolledUp"
                                    type="button"
                                    variant="primary"
                                    size="compact"
                                    icon="arrow_downward"
                                    class="absolute bottom-3 right-4 !rounded-full backdrop-blur"
                                    @click="scrollToBottom"
                                >
                                    Scroll ke Bawah
                                </AppButton>
                            </div>
                        </div>

                        <div v-if="selectedRun.error_message" class="rounded-lg bg-error-container p-3 text-xs text-error">
                            <strong>Error Exception:</strong> {{ selectedRun.error_message }}
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-outline-variant p-4">
                        <div>
                            <AppButton v-if="selectedRun.status === 'failed'" variant="warning" icon="refresh" @click="retryRun(selectedRun)">
                                Coba Ulangi Migrasi (Retry)
                            </AppButton>
                        </div>
                        <AppButton variant="secondary" @click="closeLogModal">Tutup Window Log</AppButton>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
