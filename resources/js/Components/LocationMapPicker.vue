<script setup>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import AppInput from './AppInput.vue';

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

const hasCoordinate = () => props.latitude !== null && props.longitude !== null &&
    props.latitude !== '' && props.longitude !== '';

function toNumber(value) {
    if (value === null || value === '' || value === undefined) return null;
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
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

    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 19,
        subdomains: 'abcd',
    }).addTo(map);

    L.control.zoom({ position: 'bottomright' }).addTo(map);
    L.control.attribution({ position: 'bottomleft', prefix: false })
        .addAttribution('&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OSM</a> &copy; <a href="https://carto.com/" target="_blank" rel="noopener">CARTO</a>')
        .addTo(map);

    if (savedLat !== null && savedLng !== null) {
        setMarker(savedLat, savedLng);
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
        marker.setDraggable(!props.disabled);
    }
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
    if (marker) {
        map.removeLayer(marker);
        marker = null;
    }
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
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    marker = null;
});

watch(() => [props.latitude, props.longitude], ([latitude, longitude]) => {
    const lat = toNumber(latitude);
    const lng = toNumber(longitude);
    if (lat === null || lng === null) return;
    setMarker(lat, lng);
});

watch(() => props.regencyCenter, (newCenter) => {
    if (!map || hasCoordinate()) return;
    const lat = toNumber(newCenter?.lat) ?? -7.5;
    const lng = toNumber(newCenter?.lng) ?? 109.5;
    const zoom = toNumber(newCenter?.zoom) || 11;
    map.setView([lat, lng], zoom);
}, { deep: true });

watch(() => props.disabled, () => {
    if (marker) marker.setDraggable(!props.disabled);
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
                label="Latitude"
                placeholder="-7.535000"
                :disabled="disabled"
                :error="error"
                @update:model-value="emit('update:latitude', $event === '' ? null : Number($event))"
            />
            <AppInput
                :model-value="longitude"
                type="number"
                step="0.000001"
                label="Longitude"
                placeholder="108.985000"
                :disabled="disabled"
                @update:model-value="emit('update:longitude', $event === '' ? null : Number($event))"
            />
            <AppInput
                :model-value="zoom"
                type="number"
                min="3"
                max="19"
                label="Zoom peta"
                hint="Opsional"
                :disabled="disabled"
                @update:model-value="emit('update:zoom', $event === '' ? null : Number($event))"
            />
        </div>

        <div class="overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest">
            <div ref="mapContainer" class="h-72 w-full z-0" />
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-outline-variant bg-surface-container p-3">
                <p class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <AppIcon name="location_on" />
                    Klik peta atau geser pin untuk mengatur titik lokasi tenant.
                </p>
                <AppButton
                    type="button"
                    variant="secondary"
                    size="compact"
                    icon="restart_alt"
                    :disabled="disabled"
                    @click="resetToRegency"
                >
                    Reset
                </AppButton>
            </div>
        </div>
    </div>
</template>