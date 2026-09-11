<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AppAccordion from '../../Components/AppAccordion.vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppEmptyState from '../../Components/AppEmptyState.vue';
import AppFilterPill from '../../Components/AppFilterPill.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppInput from '../../Components/AppInput.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import ProvinceLayout from '../../Layouts/ProvinceLayout.vue';
import RegencyLayout from '../../Layouts/RegencyLayout.vue';

const props = defineProps({
    releases: { type: Array, default: () => [] },
    latest_version: { type: String, default: 'Latest' },
    total_releases: { type: Number, default: 0 },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const activeLayout = computed(() => {
    if (user.value?.is_superadmin && (typeof window !== 'undefined' && window.location.pathname.startsWith('/admin'))) {
        return AdminLayout;
    }
    if (user.value?.is_province_user) {
        return ProvinceLayout;
    }
    if (user.value?.is_regency_user) {
        return RegencyLayout;
    }
    return AuthenticatedLayout;
});

const searchQuery = ref('');
const selectedCategory = ref('all');

// Expanded state per release version
const expandedReleases = reactive({});

// Default: expand the latest release
if (props.releases.length > 0) {
    expandedReleases[props.releases[0].version] = true;
}

function toggleRelease(version) {
    expandedReleases[version] = !expandedReleases[version];
}

function expandAll() {
    props.releases.forEach((r) => {
        expandedReleases[r.version] = true;
    });
}

function collapseAll() {
    props.releases.forEach((r) => {
        expandedReleases[r.version] = false;
    });
}

const allExpanded = computed(() => {
    if (props.releases.length === 0) return false;
    return props.releases.every((r) => expandedReleases[r.version]);
});

const categoryFilterItems = computed(() => [
    { value: 'all', label: 'Semua Kategori', icon: 'auto_stories' },
    { value: 'Added', label: 'Fitur Baru', icon: 'add_circle' },
    { value: 'Changed', label: 'Pembaruan', icon: 'published_with_changes' },
    { value: 'Fixed', label: 'Perbaikan', icon: 'bug_report' },
    { value: 'Security', label: 'Keamanan', icon: 'security' },
]);

const categoryMeta = {
    Added: {
        label: 'Fitur Baru',
        tone: 'success-soft',
        icon: 'add_circle',
        borderCls: 'border-secondary/30 bg-secondary-container/10',
        itemIcon: 'stars',
        itemTone: 'text-secondary',
    },
    Changed: {
        label: 'Pembaruan & Peningkatan',
        tone: 'info-soft',
        icon: 'published_with_changes',
        borderCls: 'border-primary/30 bg-primary-container/10',
        itemIcon: 'tune',
        itemTone: 'text-primary',
    },
    Fixed: {
        label: 'Perbaikan Bug',
        tone: 'warning-soft',
        icon: 'bug_report',
        borderCls: 'border-tertiary/30 bg-tertiary-fixed/10',
        itemIcon: 'healing',
        itemTone: 'text-tertiary',
    },
    Security: {
        label: 'Keamanan',
        tone: 'error-soft',
        icon: 'security',
        borderCls: 'border-error/30 bg-error-container/10',
        itemIcon: 'shield',
        itemTone: 'text-error',
    },
    Deprecated: {
        label: 'Usang (Deprecated)',
        tone: 'warning-soft',
        icon: 'warning',
        borderCls: 'border-warning/30 bg-warning-container/10',
        itemIcon: 'warning',
        itemTone: 'text-warning',
    },
    Removed: {
        label: 'Dihapus',
        tone: 'error-soft',
        icon: 'delete',
        borderCls: 'border-error/30 bg-error-container/10',
        itemIcon: 'remove_circle',
        itemTone: 'text-error',
    },
};

const totalFeaturesCount = computed(() => {
    return props.releases.reduce((acc, rel) => acc + (rel.total_changes || 0), 0);
});

const filteredReleases = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    const cat = selectedCategory.value;

    return props.releases
        .map((release) => {
            let sections = release.sections || [];

            // Filter sections by category
            if (cat !== 'all') {
                sections = sections.filter((s) => s.category.toLowerCase() === cat.toLowerCase());
                if (sections.length === 0) return null;
            }

            // Search query matching
            if (q) {
                const matchVersion = release.version.toLowerCase().includes(q);
                const matchDate = (release.date_formatted || '').toLowerCase().includes(q);
                const matchRaw = (release.raw_text || '').toLowerCase().includes(q);

                if (!matchVersion && !matchDate && !matchRaw) return null;

                // Also filter down sections & items if searching
                if (!matchVersion && !matchDate) {
                    sections = sections
                        .map((sec) => {
                            const matchingItems = (sec.items || []).filter((item) => {
                                const titleMatch = (item.title || '').toLowerCase().includes(q);
                                const subMatch = (item.sub_items || []).some((sub) => sub.raw.toLowerCase().includes(q));
                                return titleMatch || subMatch;
                            });

                            if (matchingItems.length > 0) {
                                return { ...sec, items: matchingItems };
                            }
                            return sec.html.toLowerCase().includes(q) ? sec : null;
                        })
                        .filter(Boolean);

                    if (sections.length === 0) return null;
                }
            }

            return {
                ...release,
                sections,
            };
        })
        .filter(Boolean);
});
</script>

<template>
    <component :is="activeLayout">
        <Head title="Catatan Rilis & Changelog" />

        <div class="mx-auto max-w-5xl space-y-6 pb-12">
            <!-- Header Hero Card -->
            <AppCard padded class="relative overflow-hidden bg-gradient-to-br from-surface-container-lowest via-surface-container-lowest to-primary-container/10 shadow-sm">
                <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-start gap-4">
                        <div class="grid size-13 shrink-0 place-items-center rounded-2xl bg-primary text-on-primary shadow-md">
                            <AppIcon name="history_edu" class="text-3xl" />
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <h1 class="text-xl font-bold text-on-surface sm:text-2xl">Catatan Rilis & Riwayat Pembaruan</h1>
                                <AppBadge tone="primary-soft" class="px-3 py-1 font-mono text-xs">v{{ latest_version }}</AppBadge>
                            </div>
                            <p class="mt-1 text-sm text-on-surface-variant max-w-2xl leading-relaxed">
                                Dokumentasi lengkap fitur baru, peningkatan antarmuka, optimasi performa, dan perbaikan berkala pada sistem siupk Next.
                            </p>
                        </div>
                    </div>

                    <!-- Metric Badges -->
                    <div class="flex flex-wrap items-center gap-2 sm:self-start md:self-auto">
                        <div class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-xs font-semibold text-on-surface">
                            <AppIcon name="verified" class="text-base text-secondary" />
                            <span>{{ total_releases }} Versi Rilis</span>
                        </div>
                        <div class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface-container-low px-3 py-2 text-xs font-semibold text-on-surface">
                            <AppIcon name="checklist" class="text-base text-primary" />
                            <span>{{ totalFeaturesCount }}+ Fitur & Peningkatan</span>
                        </div>
                    </div>
                </div>

                <!-- Search & Filters Toolbar -->
                <div class="mt-6 flex flex-col gap-3 pt-6 border-t border-outline-variant/60 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-1 flex-wrap items-center gap-3">
                        <div class="w-full sm:max-w-xs">
                            <AppInput
                                v-model="searchQuery"
                                placeholder="Cari fitur, modul, kode..."
                                icon="search"
                                clearable
                            />
                        </div>
                        <AppFilterPill
                            v-model="selectedCategory"
                            :items="categoryFilterItems"
                            variant="segment"
                            size="compact"
                            aria-label="Filter kategori perubahan"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 lg:pt-0">
                        <AppButton
                            size="compact"
                            variant="ghost"
                            :icon="allExpanded ? 'unfold_less' : 'unfold_more'"
                            @click="allExpanded ? collapseAll() : expandAll()"
                        >
                            {{ allExpanded ? 'Tutup Semua' : 'Buka Semua' }}
                        </AppButton>
                    </div>
                </div>
            </AppCard>

            <!-- Changelog Timeline Cards -->
            <div v-if="filteredReleases.length > 0" class="relative space-y-8">
                <div aria-hidden="true" class="absolute inset-y-0 left-4 w-0.5 -translate-x-1/2 bg-outline-variant/70"></div>
                <article
                    v-for="release in filteredReleases"
                    :key="release.version"
                    class="relative pl-11"
                >
                    <!-- Timeline Node Indicator -->
                    <div
                        class="absolute left-4 top-6 flex size-5 sm:size-6 -translate-x-1/2 items-center justify-center rounded-full border-2 bg-surface-container-lowest shadow-xs transition-transform"
                        :class="release.is_latest ? 'border-secondary' : 'border-outline'"
                    >
                        <span
                            v-if="release.is_latest"
                            class="size-2.5 rounded-full bg-secondary animate-pulse"
                        />
                        <span
                            v-else
                            class="size-2 rounded-full bg-outline"
                        />
                    </div>

                    <!-- Release Card Wrapper -->
                    <AppCard padded bordered class="overflow-hidden shadow-xs transition-all duration-200 hover:shadow-md">
                        <!-- Card Header / Collapsible Button -->
                        <div
                            class="flex cursor-pointer select-none flex-wrap items-center justify-between gap-3 pb-4"
                            :class="expandedReleases[release.version] && 'border-b border-outline-variant/60'"
                            @click="toggleRelease(release.version)"
                        >
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-lg font-bold text-primary sm:text-xl">
                                        [{{ release.version }}]
                                    </span>
                                    <span class="text-sm font-medium text-on-surface-variant">
                                        &bull; {{ release.date_formatted }}
                                    </span>
                                </div>
                                <AppBadge v-if="release.is_latest" tone="success-soft" class="flex items-center gap-1">
                                    <span class="size-1.5 rounded-full bg-secondary inline-block animate-ping" />
                                    Versi Terbaru
                                </AppBadge>
                            </div>

                            <div class="flex items-center gap-3">
                                <span class="rounded-full bg-surface-container-high px-2.5 py-1 text-xs font-semibold text-on-surface-variant">
                                    {{ release.total_changes }} Perubahan
                                </span>
                                <div class="grid size-8 place-items-center rounded-lg bg-surface-container-low text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary">
                                    <AppIcon
                                        name="expand_more"
                                        class="text-xl transition-transform duration-200 ease-out"
                                        :class="{ 'rotate-180 text-primary': expandedReleases[release.version] }"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Card Body Accordion Transition -->
                        <div
                            class="grid transition-[grid-template-rows] duration-200 ease-out"
                            :class="expandedReleases[release.version] ? 'grid-rows-[1fr] mt-5' : 'grid-rows-[0fr]'"
                        >
                            <div class="overflow-hidden space-y-6">
                                <!-- Categories Blocks -->
                                <template v-if="(release.sections || []).length > 0">
                                    <div
                                        v-for="section in release.sections"
                                        :key="section.category"
                                        class="space-y-3"
                                    >
                                        <!-- Category Section Header -->
                                        <div class="flex items-center gap-2">
                                            <AppBadge
                                                :tone="categoryMeta[section.category]?.tone || 'primary-soft'"
                                                class="flex items-center gap-1.5 px-3 py-1 text-xs font-bold"
                                            >
                                                <AppIcon :name="categoryMeta[section.category]?.icon || 'info'" class="text-sm leading-none" />
                                                <span>{{ categoryMeta[section.category]?.label || section.label }}</span>
                                            </AppBadge>
                                            <span class="text-xs font-medium text-on-surface-variant/80">
                                                ({{ (section.items || []).length }} item)
                                            </span>
                                        </div>

                                        <!-- Structured Feature Sub-Cards -->
                                        <div v-if="(section.items || []).length > 0" class="grid gap-3">
                                            <div
                                                v-for="(item, idx) in section.items"
                                                :key="idx"
                                                class="rounded-xl border border-outline-variant/50 bg-surface-container-low/40 p-4 transition-all hover:bg-surface-container-low/80 hover:border-outline-variant"
                                            >
                                                <!-- Feature Card Title -->
                                                <div class="flex items-start gap-3">
                                                    <div class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-lg bg-surface-container text-primary">
                                                        <AppIcon
                                                            :name="categoryMeta[section.category]?.itemIcon || 'check_circle'"
                                                            class="text-base"
                                                            :class="categoryMeta[section.category]?.itemTone || 'text-primary'"
                                                        />
                                                    </div>
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <div
                                                                class="changelog-item-title text-sm font-bold text-on-surface sm:text-base"
                                                                v-html="item.title_html || item.title"
                                                            />
                                                            <span
                                                                v-if="(item.sub_items || []).length > 0"
                                                                class="rounded-md bg-surface-container-high px-2 py-0.5 text-[11px] font-semibold text-on-surface-variant"
                                                            >
                                                                {{ item.sub_items.length }} Poin Rincian
                                                            </span>
                                                        </div>

                                                        <!-- Sub-items bullet list -->
                                                        <ul
                                                            v-if="(item.sub_items || []).length > 0"
                                                            class="mt-3 space-y-2 border-t border-outline-variant/40 pt-3"
                                                        >
                                                            <li
                                                                v-for="(sub, subIdx) in item.sub_items"
                                                                :key="subIdx"
                                                                class="flex items-start gap-2.5 text-sm text-on-surface-variant leading-relaxed"
                                                            >
                                                                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-outline" />
                                                                <div
                                                                    class="changelog-sub-content min-w-0 flex-1"
                                                                    v-html="sub.html"
                                                                />
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Fallback raw html if no parsed items -->
                                        <div
                                            v-else
                                            class="rounded-xl border border-outline-variant/40 bg-surface-container-low/40 p-4 changelog-content"
                                            v-html="section.html"
                                        />
                                    </div>
                                </template>

                                <!-- Fallback entire release html -->
                                <div
                                    v-else
                                    class="rounded-xl border border-outline-variant/40 bg-surface-container-low/40 p-4 changelog-content"
                                    v-html="release.html"
                                />
                            </div>
                        </div>
                    </AppCard>
                </article>
            </div>

            <!-- Empty State -->
            <AppEmptyState
                v-else
                icon="search_off"
                title="Tidak Ada Catatan Rilis yang Cocok"
                description="Coba ubah kata kunci pencarian atau reset filter kategori untuk melihat riwayat rilis lainnya."
            >
                <div class="mt-4 flex justify-center gap-2">
                    <AppButton
                        size="compact"
                        variant="secondary"
                        icon="restart_alt"
                        @click="searchQuery = ''; selectedCategory = 'all'"
                    >
                        Reset Filter
                    </AppButton>
                </div>
            </AppEmptyState>
        </div>
    </component>
</template>

<style>
.changelog-item-title strong {
    color: var(--color-on-surface);
    font-weight: 700;
}
.changelog-item-title code,
.changelog-sub-content code,
.changelog-content code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.8125rem;
    background-color: var(--color-surface-container-high);
    color: var(--color-primary);
    padding: 0.125rem 0.375rem;
    border-radius: 0.375rem;
    font-weight: 600;
}
.changelog-sub-content strong,
.changelog-content strong {
    color: var(--color-on-surface);
    font-weight: 600;
}
.changelog-sub-content p,
.changelog-item-title p {
    display: inline;
    margin: 0;
}
.changelog-sub-content a,
.changelog-content a {
    color: var(--color-primary);
    text-decoration: underline;
    font-weight: 500;
}
.changelog-content ul {
    list-style-type: disc;
    padding-left: 1.25rem;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}
.changelog-content li {
    margin-top: 0.375rem;
    margin-bottom: 0.375rem;
}
</style>
