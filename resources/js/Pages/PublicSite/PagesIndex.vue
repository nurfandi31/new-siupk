<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
    pages: { type: Object, required: true },
    search: { type: String, default: '' },
});

function formatDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString('id-ID', { dateStyle: 'long' });
}
</script>

<template>
    <Head :title="`Halaman — ${organization.name}`">
        <meta head-key="description" name="description" :content="`Halaman informasi dari ${organization.name} — profil, layanan, dan dokumen publik.`" />
        <meta head-key="og:title" property="og:title" :content="`Halaman — ${organization.name}`" />
        <meta head-key="og:description" property="og:description" :content="`Halaman informasi dari ${organization.name}.`" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:url" property="og:url" :content="$page.url" />
        <meta head-key="twitter:card" name="twitter:card" content="summary" />
        <meta head-key="twitter:title" name="twitter:title" :content="`Halaman — ${organization.name}`" />
    </Head>

    <div class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        <PublicSiteHeader :organization="organization" active="halaman" />

        <main class="flex-1">
            <section class="border-b border-outline-variant/40 bg-gradient-to-b from-primary-container/40 to-surface">
                <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                    <span class="inline-flex items-center gap-2 rounded-full bg-primary-container px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-on-primary-container">
                        <AppIcon name="description" />
                        Halaman Informasi
                    </span>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-primary sm:text-4xl">
                        Halaman &amp; Dokumen Publik {{ organization.name }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-base leading-relaxed text-on-surface-variant">
                        Kumpulan halaman statis yang dikelola admin sekretariat — profil lembaga, layanan, AD/ART, dan dokumen publik lain.
                    </p>

                    <form class="mt-6 flex max-w-xl gap-2" @submit.prevent>
                        <div class="relative flex-1">
                            <AppIcon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant" />
                            <input
                                type="search"
                                name="q"
                                :value="search"
                                placeholder="Cari halaman…"
                                class="h-11 w-full rounded-xl border border-outline-variant bg-surface-container-lowest pl-10 pr-4 text-sm text-primary placeholder:text-outline transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                            >
                        </div>
                        <button type="submit" class="inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-primary px-5 text-sm font-bold text-on-primary shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg active:scale-[0.97] active:shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2">
                            Cari
                        </button>
                    </form>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div v-if="pages.data.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="page in pages.data"
                        :key="page.slug"
                        :href="`/p/${page.slug}`"
                        class="group flex h-full flex-col gap-3 rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg"
                    >
                        <div class="flex items-center gap-3">
                            <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary-container text-primary transition group-hover:bg-primary group-hover:text-on-primary">
                                <AppIcon name="description" class="text-xl" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-on-surface-variant">
                                    Diperbarui {{ formatDateTime(page.updated_at) }}
                                </p>
                            </div>
                        </div>
                        <h2 class="text-lg font-bold leading-snug text-on-surface transition group-hover:text-primary">
                            {{ page.title }}
                        </h2>
                        <p v-if="page.excerpt" class="flex-1 text-sm leading-relaxed text-on-surface-variant line-clamp-3">
                            {{ page.excerpt }}
                        </p>
                        <span class="inline-flex items-center gap-1 text-sm font-semibold text-primary">
                            Buka halaman
                            <AppIcon name="arrow_forward" class="text-xs transition-transform duration-300 group-hover:translate-x-0.5" />
                        </span>
                    </Link>
                </div>

                <div v-else class="mx-auto max-w-md py-16 text-center">
                    <span class="mx-auto grid size-16 place-items-center rounded-full bg-surface-container">
                        <AppIcon name="description" class="text-3xl text-on-surface-variant" />
                    </span>
                    <h2 class="mt-4 text-lg font-bold text-on-surface">{{ search ? 'Tidak ada halaman yang cocok' : 'Belum ada halaman' }}</h2>
                    <p class="mt-2 text-sm text-on-surface-variant">
                        {{ search ? `Tidak ditemukan halaman untuk pencarian "${search}". Coba kata kunci lain.` : 'Halaman statis akan tampil di sini setelah admin mempublikasikannya melalui menu Website → Halaman.' }}
                    </p>
                </div>

                <!-- Pagination -->
                <nav v-if="pages.last_page > 1" class="mt-10 flex items-center justify-center gap-2" aria-label="Navigasi halaman">
                    <Link
                        v-if="pages.prev_page_url"
                        :href="pages.prev_page_url"
                        class="inline-flex min-h-10 items-center gap-1 rounded-full border border-outline-variant px-4 text-sm font-semibold text-on-surface hover:bg-surface-container"
                    >
                        <AppIcon name="chevron_left" />
                        Sebelumnya
                    </Link>
                    <span class="px-3 text-sm text-on-surface-variant">Halaman {{ pages.current_page }} dari {{ pages.last_page }}</span>
                    <Link
                        v-if="pages.next_page_url"
                        :href="pages.next_page_url"
                        class="inline-flex min-h-10 items-center gap-1 rounded-full border border-outline-variant px-4 text-sm font-semibold text-on-surface hover:bg-surface-container"
                    >
                        Berikutnya
                        <AppIcon name="chevron_right" />
                    </Link>
                </nav>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-outline-variant/60 bg-surface-container-lowest py-8">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <p class="text-sm font-semibold text-on-surface">
                    © {{ new Date().getFullYear() }} {{ organization.legal_name }}
                </p>
                <p class="mt-1.5 text-xs text-on-surface-variant">
                    Dikelola dengan
                    <a href="/" class="font-semibold text-primary hover:underline">siupk Next</a>
                    — Sistem Informasi Dana Bergulir Masyarakat
                </p>
            </div>
        </footer>
    </div>
</template>
