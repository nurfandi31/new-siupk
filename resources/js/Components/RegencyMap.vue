<script setup>
import { Link } from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import AppBadge from './AppBadge.vue';
import AppButton from './AppButton.vue';
import AppCard from './AppCard.vue';
import AppIcon from './AppIcon.vue';
import { useMoney } from '../composables/useMoney';

const props = defineProps({
    kecamatans: { type: Array, required: true, default: () => [] },
    regencyName: { type: String, default: 'Kabupaten' },
    regencyCenter: { type: Object, default: () => ({ lat: -7.5, lng: 109.5, zoom: 10 }) },
    year: { type: Number, required: true },
    month: { type: [Number, String], default: '' },
    selectedTenantId: { type: [Number, String], default: '' },
});

const emit = defineEmits(['select-tenant']);

const { money } = useMoney();

const mapContainer = ref(null);
let mapInstance = null;
let markersLayer = null;

const activeMetric = ref('turnover');
const searchQuery = ref('');
const selectedKecamatan = ref(null);
const isExpanded = ref(false);

const metricOptions = [
    { key: 'turnover', label: 'Perputaran Dana', icon: 'sync_alt' },
    { key: 'assets', label: 'Akumulasi Aset', icon: 'account_balance' },
    { key: 'npl', label: 'Rasio NPL (Risiko)', icon: 'health_and_safety' },
    { key: 'loans', label: 'Pinjaman Aktif', icon: 'credit_score' },
];

const filteredKecamatans = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.kecamatans;
    return props.kecamatans.filter(k =>
        (k.name || '').toLowerCase().includes(q) ||
        (k.district_code || k.code || '').toLowerCase().includes(q),
    );
});

function compactNumber(val) {
    const n = Number(val || 0);
    if (Math.abs(n) >= 1_000_000_000) {
        return `Rp ${(n / 1_000_000_000).toFixed(1)} M`;
    }
    if (Math.abs(n) >= 1_000_000) {
        return `Rp ${(n / 1_000_000).toFixed(0)} Jt`;
    }
    if (Math.abs(n) >= 1_000) {
        return `Rp ${(n / 1_000).toFixed(0)} Rb`;
    }
    return money(n);
}

function getMarkerColor(kec) {
    if (activeMetric.value === 'npl') {
        const ratio = Number(kec.npl_ratio || 0);
        if (ratio <= 5.0) return { bg: '#0b5c2a', border: '#8fd4b0', label: 'Sehat' };
        if (ratio <= 10.0) return { bg: '#0b3d66', border: '#81a8d7', label: 'Cukup Sehat' };
        if (ratio <= 25.0) return { bg: '#8f5300', border: '#ffddb0', label: 'Kurang Sehat' };
        return { bg: '#ba1a1a', border: '#ffdad6', label: 'Tidak Sehat' };
    }

    if (activeMetric.value === 'assets') {
        return { bg: '#002746', border: '#81a8d7', label: 'Aset' };
    }

    if (activeMetric.value === 'loans') {
        return { bg: '#0b3d2a', border: '#8fd4b0', label: 'Pinjaman' };
    }

    return { bg: '#0b3d66', border: '#a2cafa', label: 'Perputaran' };
}

function getMetricValueFormatted(kec) {
    if (activeMetric.value === 'npl') {
        return `${Number(kec.npl_ratio || 0).toFixed(1)}% NPL`;
    }
    if (activeMetric.value === 'assets') {
        return compactNumber(kec.total_assets);
    }
    if (activeMetric.value === 'loans') {
        return `${kec.active_loans || 0} Pinj`;
    }
    return compactNumber(kec.turnover);
}

function initMap() {
    if (!mapContainer.value || mapInstance) return;

    const centerLat = props.regencyCenter?.lat || -7.535;
    const centerLng = props.regencyCenter?.lng || 108.985;
    const initialZoom = props.regencyCenter?.zoom || 11;

    mapInstance = L.map(mapContainer.value, {
        center: [centerLat, centerLng],
        zoom: initialZoom,
        zoomControl: false,
        attributionControl: false,
    });

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 18,
        subdomains: 'abcd',
    }).addTo(mapInstance);

    L.control.attribution({ position: 'bottomright', prefix: false })
        .addAttribution('&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OSM</a> &copy; <a href="https://carto.com/" target="_blank">CARTO</a>')
        .addTo(mapInstance);

    markersLayer = L.layerGroup().addTo(mapInstance);

    renderMarkers();
}

function renderMarkers() {
    if (!mapInstance || !markersLayer) return;

    markersLayer.clearLayers();

    const bounds = [];

    props.kecamatans.forEach((kec) => {
        if (kec.lat === undefined || kec.lng === undefined) return;

        const latLng = [kec.lat, kec.lng];
        bounds.push(latLng);

        const color = getMarkerColor(kec);
        const badgeText = getMetricValueFormatted(kec);
        const isSelected = selectedKecamatan.value?.tenant_id === kec.tenant_id;

        const iconHtml = `
            <div class="relative flex flex-col items-center group cursor-pointer" style="transform: translate(-50%, -100%);">
                <div class="flex items-center gap-1.5 px-2 py-1 rounded-full shadow-md transition-all duration-200 ${isSelected ? 'ring-2 ring-offset-2 ring-primary scale-110' : 'hover:scale-105'}"
                     style="background-color: ${color.bg}; color: #ffffff; border: 1.5px solid ${color.border}; white-space: nowrap;">
                    <span class="size-2 rounded-full bg-white animate-pulse"></span>
                    <span class="text-[11px] font-bold tracking-tight">${kec.name}</span>
                    <span class="text-[10px] opacity-90 font-medium px-1 rounded bg-black/20">${badgeText}</span>
                </div>
                <div class="w-0.5 h-2" style="background-color: ${color.bg};"></div>
                <div class="size-1.5 rounded-full" style="background-color: ${color.bg};"></div>
            </div>
        `;

        const customIcon = L.divIcon({
            className: 'custom-leaflet-marker',
            html: iconHtml,
            iconSize: [0, 0],
            iconAnchor: [0, 0],
        });

        const marker = L.marker(latLng, { icon: customIcon });

        marker.on('click', () => {
            selectKecamatan(kec);
        });

        markersLayer.addLayer(marker);
    });

    if (bounds.length > 0 && !selectedKecamatan.value) {
        mapInstance.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
    }
}

function selectKecamatan(kec) {
    selectedKecamatan.value = kec;
    emit('select-tenant', kec.tenant_id);

    if (mapInstance && kec.lat && kec.lng) {
        mapInstance.flyTo([kec.lat, kec.lng], 13, { duration: 0.8 });
    }
    renderMarkers();
}

function resetView() {
    selectedKecamatan.value = null;
    emit('select-tenant', '');
    if (!mapInstance) return;

    const bounds = props.kecamatans
        .filter(k => k.lat && k.lng)
        .map(k => [k.lat, k.lng]);

    if (bounds.length > 0) {
        mapInstance.fitBounds(bounds, { padding: [40, 40], maxZoom: 13 });
    } else {
        mapInstance.setView(
            [props.regencyCenter?.lat || -7.535, props.regencyCenter?.lng || 108.985],
            props.regencyCenter?.zoom || 11,
        );
    }
    renderMarkers();
}

function zoomIn() {
    mapInstance?.zoomIn();
}

function zoomOut() {
    mapInstance?.zoomOut();
}

function toggleFullscreen() {
    isExpanded.value = !isExpanded.value;
    nextTick(() => {
        mapInstance?.invalidateSize();
    });
}

watch(activeMetric, () => {
    renderMarkers();
});

watch(() => props.kecamatans, () => {
    renderMarkers();
}, { deep: true });

watch(() => props.selectedTenantId, (newId) => {
    if (!newId) {
        selectedKecamatan.value = null;
    } else {
        const target = props.kecamatans.find(k => k.tenant_id === Number(newId));
        if (target) {
            selectKecamatan(target);
        }
    }
});

onMounted(() => {
    nextTick(() => {
        initMap();
    });
});

onUnmounted(() => {
    if (mapInstance) {
        mapInstance.remove();
        mapInstance = null;
    }
});
</script>

<template>
    <AppCard
        class="relative overflow-hidden transition-all duration-300"
        :class="isExpanded ? 'fixed inset-4 z-50 shadow-2xl flex flex-col' : 'w-full'"
        :padded="false"
    >
        <div class="border-b border-outline-variant bg-surface-container-lowest px-4 py-3 sm:px-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-3">
                    <AppIcon name="map" tone="primary" :container-size="9" />
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="font-bold text-primary">Peta Sebaran BUMDesma {{ regencyName }}</h2>
                            <AppBadge tone="primary-soft">{{ kecamatans.length }} Kecamatan</AppBadge>
                        </div>
                        <p class="text-xs text-on-surface-variant">
                            Visualisasi spasial sebaran BUMDesma, total perputaran dana, akumulasi aset, dan rasio NPL per wilayah.
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-xs font-semibold text-on-surface-variant mr-1 hidden sm:inline">Metrik Peta:</span>
                    <button
                        v-for="opt in metricOptions"
                        :key="opt.key"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors cursor-pointer"
                        :class="activeMetric === opt.key
                            ? 'bg-primary text-on-primary shadow-xs'
                            : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high'"
                        @click="activeMetric = opt.key"
                    >
                        <AppIcon :name="opt.icon" :class="activeMetric === opt.key ? 'text-on-primary' : 'text-on-surface-variant'" />
                        <span>{{ opt.label }}</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="relative w-full" :class="isExpanded ? 'flex-1 min-h-[500px]' : 'h-[460px] sm:h-[520px]'">
            <div ref="mapContainer" class="size-full z-0"></div>

            <div class="absolute right-3 top-3 z-10 flex flex-col gap-2">
                <div class="flex flex-col overflow-hidden rounded-lg border border-outline-variant bg-surface-container-lowest shadow-md">
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center text-on-surface hover:bg-surface-container cursor-pointer border-b border-outline-variant/60"
                        title="Perbesar Peta"
                        @click="zoomIn"
                    >
                        <AppIcon name="add" />
                    </button>
                    <button
                        type="button"
                        class="flex size-9 items-center justify-center text-on-surface hover:bg-surface-container cursor-pointer"
                        title="Perkecil Peta"
                        @click="zoomOut"
                    >
                        <AppIcon name="remove" />
                    </button>
                </div>

                <button
                    type="button"
                    class="flex size-9 items-center justify-center rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface shadow-md hover:bg-surface-container cursor-pointer"
                    title="Reset Posisi Peta"
                    @click="resetView"
                >
                    <AppIcon name="center_focus_strong" />
                </button>

                <button
                    type="button"
                    class="flex size-9 items-center justify-center rounded-lg border border-outline-variant bg-surface-container-lowest text-on-surface shadow-md hover:bg-surface-container cursor-pointer"
                    :title="isExpanded ? 'Kecilkan Peta' : 'Layar Penuh'"
                    @click="toggleFullscreen"
                >
                    <AppIcon :name="isExpanded ? 'fullscreen_exit' : 'fullscreen'" />
                </button>
            </div>

            <div class="absolute bottom-3 left-3 z-10 hidden sm:block max-w-xs rounded-xl border border-outline-variant bg-surface-container-lowest/95 p-3 shadow-md backdrop-blur-xs">
                <div class="flex items-center justify-between gap-2 border-b border-outline-variant/50 pb-1.5 mb-2">
                    <span class="text-[11px] font-bold text-primary uppercase tracking-wider">Legenda Indikator</span>
                    <span class="text-[10px] text-on-surface-variant font-medium">{{ metricOptions.find(m => m.key === activeMetric)?.label }}</span>
                </div>

                <div v-if="activeMetric === 'npl'" class="space-y-1 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-secondary"></span> Sehat</span>
                        <span class="font-mono text-[11px] text-on-surface-variant">≤ 5.0%</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-primary-container"></span> Cukup Sehat</span>
                        <span class="font-mono text-[11px] text-on-surface-variant">5.1 – 10.0%</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-tertiary-container"></span> Kurang Sehat</span>
                        <span class="font-mono text-[11px] text-on-surface-variant">10.1 – 25.0%</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-error"></span> Tidak Sehat / Macet</span>
                        <span class="font-mono text-[11px] text-on-surface-variant">> 25.0%</span>
                    </div>
                </div>

                <div v-else class="space-y-1.5 text-xs text-on-surface-variant">
                    <p class="text-[11px]">Pin lokasi merepresentasikan titik kantor BUMDesma / UPK per kecamatan.</p>
                    <div class="flex items-center gap-2 pt-1 border-t border-outline-variant/30 text-[11px] text-primary font-medium">
                        <AppIcon name="touch_app" />
                        <span>Klik pin untuk melihat rincian keuangan</span>
                    </div>
                </div>
            </div>

            <div
                v-if="selectedKecamatan"
                class="absolute left-3 top-3 z-10 w-80 max-w-[calc(100%-4rem)] rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xl backdrop-blur-xs transition-all"
            >
                <div class="flex items-start justify-between gap-2 border-b border-outline-variant/60 pb-3">
                    <div>
                        <span class="font-mono text-[10px] font-bold text-on-surface-variant uppercase tracking-wider">
                            KODE: {{ selectedKecamatan.district_code || selectedKecamatan.code }}
                        </span>
                        <h3 class="text-base font-bold text-primary">{{ selectedKecamatan.name }}</h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1 text-on-surface-variant hover:bg-surface-container cursor-pointer"
                        @click="selectedKecamatan = null"
                    >
                        <AppIcon name="close" />
                    </button>
                </div>

                <div class="mt-3 flex items-center justify-between">
                    <span class="text-xs text-on-surface-variant font-medium">Status Risiko NPL</span>
                    <AppBadge :tone="selectedKecamatan.npl_tone || 'primary'">
                        {{ selectedKecamatan.npl_status || 'Sehat' }} ({{ selectedKecamatan.npl_ratio }}%)
                    </AppBadge>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-surface-container-low p-2.5">
                        <span class="text-[10px] text-on-surface-variant font-medium">Perputaran Dana</span>
                        <p class="mt-0.5 font-bold text-primary">{{ money(selectedKecamatan.turnover) }}</p>
                    </div>
                    <div class="rounded-lg bg-surface-container-low p-2.5">
                        <span class="text-[10px] text-on-surface-variant font-medium">Total Aset</span>
                        <p class="mt-0.5 font-bold text-primary">{{ money(selectedKecamatan.total_assets) }}</p>
                    </div>
                    <div class="rounded-lg bg-surface-container-low p-2.5">
                        <span class="text-[10px] text-on-surface-variant font-medium">Pinjaman Aktif</span>
                        <p class="mt-0.5 font-bold text-primary">{{ selectedKecamatan.active_loans }} Pinjaman</p>
                    </div>
                    <div class="rounded-lg bg-surface-container-low p-2.5">
                        <span class="text-[10px] text-on-surface-variant font-medium">Kas & Bank</span>
                        <p class="mt-0.5 font-bold text-primary">{{ money(selectedKecamatan.cash) }}</p>
                    </div>
                </div>

                <div class="mt-2.5 flex items-center justify-between text-xs text-on-surface-variant px-1">
                    <span>{{ selectedKecamatan.groups_count }} Kelompok</span>
                    <span>•</span>
                    <span>{{ selectedKecamatan.members_count }} Anggota</span>
                </div>

                <div class="mt-3 pt-3 border-t border-outline-variant/60">
                    <Link
                        :href="`/regency/reports/balance-sheet?tenant_id=${selectedKecamatan.tenant_id}&year=${year}&month=${month || ''}`"
                        class="block w-full"
                    >
                        <AppButton variant="secondary" size="compact" class="w-full justify-center" icon="arrow_forward">
                            Buka Neraca Kecamatan
                        </AppButton>
                    </Link>
                </div>
            </div>
        </div>
    </AppCard>
</template>

<style>
.custom-leaflet-marker {
    background: transparent !important;
    border: none !important;
}
.leaflet-popup-content-wrapper {
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
}
</style>
