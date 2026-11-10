<script setup>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import AppInput from './AppInput.vue';
import { useI18n } from '../i18n';

// Leaflet menebak URL ikon dari lokasi CSS. Saat di-bundle Vite, tebakan itu
// gagal dan pin tampil sebagai gambar rusak. Daftarkan aset secara eksplisit.
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

const { t } = useI18n();

const props = defineProps({
    latitude: { type: [Number, String], default: null },
    longitude: { type: [Number, String], default: null },
    zoom: { type: [Number, String], default: 13 },
    regencyCenter: { type: Object, default: () => ({ lat: -7.5, lng: 109.5, zoom: 11 }) },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:latitude', 'update:longitude', 'update:zoom']);

const mapContainer = ref(null);
let map = null;
let marker = null;
let resizeObserver = null;

function toNumber(value) {
    if (value === null || value === '' || value === undefined) return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
}

const hasCoordinate = () => toNumber(props.latitude) !== null && toNumber(props.longitude) !== null;

function setMarkerDraggable(enabled) {
    if (!marker) return;
    marker.options.draggable = enabled;
    // Leaflet exposes draggable toggling through the marker drag handler;
    // Marker.setDraggable() does not exist in Leaflet 1.x.
    if (marker.dragging) {
        enabled ? marker.dragging.enable() : marker.dragging.disable();
    }
}

function initMap() {
    if (!mapContainer.value || map) return;

    const savedLat = toNumber(props.latitude);
    const savedLng = toNumber(props.longitude);
    const center = savedLat !== null && savedLng !== null
        ? [savedLat, savedLng]
        : [toNumber(props.regencyCenter?.lat) ?? -7.5, toNumber(props.regencyCenter?.lng) ?? 109.5];

    map = L.map(mapContainer.value, {
        center,
        zoom: savedLat !== null ? Number(props.zoom || 13) : toNumber(props.regencyCenter?.zoom) || 11,
        zoomControl: false,
        scrollWheelZoom: !props.disabled,
        dragging: !props.disabled,
        doubleClickZoom: !props.disabled,
    });

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
    }).addTo(map);

    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.control.attribution({ position: 'bottomleft', prefix: false })
        .addAttribution('&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>')
        .addTo(map);

    if (savedLat !== null && savedLng !== null) {
        setMarker(savedLat, savedLng);
    } else {
        // Tanpa koordinat tersimpan, peta memakai zoom wilayah. Samakan
        // nilai input zoom agar tidak menampilkan angka yang berbeda.
        emit('update:zoom', Number(map.getZoom()));
    }

    map.on('click', (event) => {
        if (props.disabled) return;
        updatePosition(event.latlng.lat, event.latlng.lng);
    });

    map.on('zoomend', () => {
        if (hasCoordinate()) {
            updateZoom();
        }
    });
}

function setMarker(latitude, longitude) {
    if (!map) return;
    const latLng = [latitude, longitude];

    if (!marker) {
        marker = L.marker(latLng, { draggable: !props.disabled }).addTo(map);
        marker.on('dragend', () => {
            const position = marker.getLatLng();
            updatePosition(position.lat, position.lng);
        });
    } else {
        marker.setLatLng(latLng);
        setMarkerDraggable(!props.disabled);
    }
}

function removeMarker() {
    if (!map || !marker) return;
    map.removeLayer(marker);
    marker = null;
}

function updatePosition(latitude, longitude) {
    const lat = Number(latitude.toFixed(6));
    const lng = Number(longitude.toFixed(6));
    emit('update:latitude', lat);
    emit('update:longitude', lng);
    emit('update:zoom', Number(map?.getZoom() || props.zoom || 13));
    setMarker(lat, lng);
}

function updateZoom() {
    if (!map) return;
    emit('update:zoom', Number(map.getZoom()));
}

function resetToRegency() {
    if (!map) return;
    emit('update:latitude', null);
    emit('update:longitude', null);
    emit('update:zoom', null);
    removeMarker();
    map.setView(
        [toNumber(props.regencyCenter?.lat) ?? -7.5, toNumber(props.regencyCenter?.lng) ?? 109.5],
        toNumber(props.regencyCenter?.zoom) || 11,
    );
}

function invalidateSize() {
    map?.invalidateSize();
}

onMounted(() => {
    initMap();

    // Peta berada di dalam kartu responsif; pastikan ukuran ikut berubah
    // saat kontainer melebar/menyempit agar tile tetap tampil penuh.
    if (typeof ResizeObserver !== 'undefined' && mapContainer.value) {
        resizeObserver = new ResizeObserver(() => invalidateSize());
        resizeObserver.observe(mapContainer.value);
    }
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    resizeObserver = null;
    map?.remove();
    map = null;
    marker = null;
});

watch(() => [props.latitude, props.longitude], ([latitude, longitude]) => {
    const lat = toNumber(latitude);
    const lng = toNumber(longitude);
    if (lat === null || lng === null) {
        removeMarker();
        return;
    }
    setMarker(lat, lng);

    // Input koordinat manual (mis. hasil salin dari Google Maps) harus
    // langsung menyinkronkan peta, bukan hanya memindahkan pin di luar layar.
    if (map && !map.getBounds().contains([lat, lng])) {
        map.panTo([lat, lng]);
    }
});

watch(() => props.zoom, (value) => {
    if (!map) return;
    const zoom = toNumber(value);
    if (zoom === null) return;
    const clamped = Math.max(map.getMinZoom(), Math.min(map.getMaxZoom(), zoom));
    if (map.getZoom() !== clamped) {
        map.setZoom(clamped, { animate: false });
    }
});

watch(() => props.regencyCenter, (newCenter) => {
    if (!map || hasCoordinate()) return;
    const lat = toNumber(newCenter?.lat) ?? -7.5;
    const lng = toNumber(newCenter?.lng) ?? 109.5;
    const zoom = toNumber(newCenter?.zoom) || 11;
    map.setView([lat, lng], zoom);
    emit('update:zoom', zoom);
}, { deep: true });

watch(() => props.disabled, () => {
    setMarkerDraggable(!props.disabled);
});

defineExpose({ invalidateSize });
</script>

<template>
    <div class="space-y-3">
        <div class="grid gap-4 sm:grid-cols-3">
            <AppInput
                :model-value="latitude"
                type="number"
                step="0.000001"
                :label="t('Latitude')"
                placeholder="-7.535000"
                :disabled="disabled"
                :error="error"
                @update:model-value="emit('update:latitude', $event === '' ? null : Number($event))"
            />
            <AppInput
                :model-value="longitude"
                type="number"
                step="0.000001"
                :label="t('Longitude')"
                placeholder="108.985000"
                :disabled="disabled"
                @update:model-value="emit('update:longitude', $event === '' ? null : Number($event))"
            />
            <AppInput
                :model-value="zoom"
                type="number"
                min="3"
                max="19"
                :label="t('Zoom peta')"
                :hint="t('Opsional')"
                :disabled="disabled"
                @update:model-value="emit('update:zoom', $event === '' ? null : Number($event))"
            />
        </div>

        <div class="overflow-hidden rounded-xl chip-shadow bg-surface-container-lowest ring-1 ring-outline-variant/40">
            <div ref="mapContainer" class="h-72 w-full z-0" />
            <div class="flex flex-wrap items-center justify-between gap-2 inset-divider-t bg-surface-container p-3">
                <p class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <AppIcon name="location_on" />
                    {{ t('Klik peta atau geser pin untuk mengatur titik lokasi tenant.') }}
                </p>
                <AppButton
                    type="button"
                    variant="secondary"
                    size="compact"
                    icon="restart_alt"
                    :disabled="disabled"
                    @click="resetToRegency"
                >
                    {{ t('Reset') }}
                </AppButton>
            </div>
        </div>
    </div>
</template>