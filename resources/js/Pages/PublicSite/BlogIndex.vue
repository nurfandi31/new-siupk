<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
    posts: { type: Object, required: true },
    search: { type: String, default: '' },
});

function formatDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString('id-ID', { dateStyle: 'long' });
}
</script>

<template>
    <Head :title="`Berita — ${organization.name}`">
        <meta head-key="description" name="description" :content="`Berita terbaru dari ${organization.name} — informasi kegiatan dan pengumuman.`" />
        <meta head-key="og:title" property="og:title" :content="`Berita — ${organization.name}`" />
        <meta head-key="og:description" property="og:description" :content="`Berita terbaru dari ${organization.name}.`" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:url" property="og:url" :content="$page.url" />
        <meta head-key="twitter:card" name="twitter:card" content="summary" />
        <meta head-key="twitter:title" name="twitter:title" :content="`Berita — ${organization.name}`" />
    </Head>

    <div class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        <!-- Top bar: organization identity -->
        <header class="sticky top-0 z-40 border-b border-outline-variant/60 bg-surface-container-lowest/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
                <Link href="/" class="flex min-w-0 items-center gap-3">
                    <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary-container shadow-md">
                        <img
                            v-if="organization.logo_url"
                            :src="organization.logo_url"
                            :alt="`Logo ${organization.name}`"
                            class="size-full object-contain"
                        >
                        <span v-else class="text-lg font-extrabold text-on-primary-container">{{ organization.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold leading-tight text-on-surface">{{ organization.name }}</p>
                        <p v-if="organization.regency_name || organization.district_name" class="truncate text-xs text-on-surface-variant">
                            {{ [organization.district_name, organization.regency_name].filter(Boolean).join(', ') }}
                        </p>
                    </div>
                </Link>

                <nav class="flex items-center gap-2">
                    <Link href="/berita" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary-container px-4 text-sm font-semibold text-on-primary-container">
                        <AppIcon name="article" />
                        Berita
                    </Link>
                    <Link href="/kontak" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary-container px-4 text-sm font-semibold text-on-primary-container">
                        <AppIcon name="mail" />
                        Kontak
                    </Link>
                    <Link href="/login" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-on-primary shadow-md transition hover:bg-primary-deep">
                        <AppIcon name="login" />
                        Masuk Sistem
                    </Link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <section class="border-b border-outline-variant/40 bg-gradient-to-b from-primary-container/40 to-surface">
                <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                    <span class="inline-flex items-center gap-2 rounded-full bg-primary-container px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-on-primary-container">
                        <AppIcon name="newspaper" />
                        Berita &amp; Pengumuman
                    </span>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-primary sm:text-4xl">
                        Kabar Terbaru dari {{ organization.name }}
                    </h1>
                    <p class="mt-3 max-w-2xl text-base leading-relaxed text-on-surface-variant">
                        Informasi kegiatan, pengumuman, dan laporan seputar pengelolaan dana bergulir masyarakat.
                    </p>

                    <form class="mt-6 flex max-w-xl gap-2" @submit.prevent>
                        <div class="relative flex-1">
                            <AppIcon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant" />
                            <input
                                type="search"
                                name="q"
                                :value="search"
                                placeholder="Cari berita…"
                                class="w-full rounded-full border border-outline-variant bg-surface-container-lowest py-2.5 pl-10 pr-4 text-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none"
                            >
                        </div>
                        <button type="submit" class="inline-flex min-h-11 items-center rounded-full bg-primary px-6 text-sm font-semibold text-on-primary shadow-md transition hover:bg-primary-deep">
                            Cari
                        </button>
                    </form>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div v-if="posts.data.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <article
                        v-for="post in posts.data"
                        :key="post.slug"
                        class="group flex flex-col overflow-hidden rounded-2xl border border-outline-variant/40 bg-surface-container-lowest shadow-md transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <Link :href="`/berita/${post.slug}`" class="block aspect-video overflow-hidden bg-surface-container">
                            <img
                                v-if="post.cover_image_url"
                                :src="post.cover_image_url"
                                :alt="post.title"
                                class="size-full object-cover transition duration-300 group-hover:scale-105"
                            >
                            <div v-else class="grid size-full place-items-center text-on-surface-variant/50">
                                <AppIcon name="image" class="text-4xl" />
                            </div>
                        </Link>
                        <div class="flex flex-1 flex-col p-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                                {{ formatDateTime(post.published_at) }}
                            </p>
                            <h2 class="mt-2 text-lg font-bold leading-snug text-on-surface">
                                <Link :href="`/berita/${post.slug}`" class="transition hover:text-primary">{{ post.title }}</Link>
                            </h2>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-on-surface-variant">
                                {{ post.excerpt || 'Baca selengkapnya…' }}
                            </p>
                            <Link :href="`/berita/${post.slug}`" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                                Baca selengkapnya
                                <AppIcon name="arrow_forward" />
                            </Link>
                        </div>
                    </article>
                </div>

                <div v-else class="mx-auto max-w-md py-16 text-center">
                    <span class="mx-auto grid size-16 place-items-center rounded-full bg-surface-container">
                        <AppIcon name="newspaper" class="text-3xl text-on-surface-variant" />
                    </span>
                    <h2 class="mt-4 text-lg font-bold text-on-surface">{{ search ? 'Tidak ada berita yang cocok' : 'Belum ada berita' }}</h2>
                    <p class="mt-2 text-sm text-on-surface-variant">
                        {{ search ? `Tidak ditemukan berita untuk pencarian "${search}". Coba kata kunci lain.` : 'Berita dan pengumuman akan tampil di sini.' }}
                    </p>
                </div>

                <!-- Pagination -->
                <nav v-if="posts.last_page > 1" class="mt-10 flex items-center justify-center gap-2" aria-label="Navigasi halaman berita">
                    <Link
                        v-if="posts.prev_page_url"
                        :href="posts.prev_page_url"
                        class="inline-flex min-h-10 items-center gap-1 rounded-full border border-outline-variant px-4 text-sm font-semibold text-on-surface hover:bg-surface-container"
                    >
                        <AppIcon name="chevron_left" />
                        Sebelumnya
                    </Link>
                    <span class="px-3 text-sm text-on-surface-variant">Halaman {{ posts.current_page }} dari {{ posts.last_page }}</span>
                    <Link
                        v-if="posts.next_page_url"
                        :href="posts.next_page_url"
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
