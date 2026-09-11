<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
    post: { type: Object, required: true },
});

function formatDateTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleDateString('id-ID', { dateStyle: 'long' });
}
</script>

<template>
    <Head :title="`${post.title} — ${organization.name}`">
        <meta head-key="description" name="description" :content="post.meta_description ?? post.excerpt ?? `Berita dari ${organization.name}.`" />
        <meta head-key="og:title" property="og:title" :content="post.title" />
        <meta head-key="og:description" property="og:description" :content="post.meta_description ?? post.excerpt ?? `Berita dari ${organization.name}.`" />
        <meta head-key="og:type" property="og:type" content="article" />
        <meta head-key="og:url" property="og:url" :content="$page.url" />
        <meta v-if="post.cover_image_url" head-key="og:image" property="og:image" :content="post.cover_image_url" />
        <meta v-if="post.published_at" head-key="article:published_time" property="article:published_time" :content="post.published_at" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="post.title" />
        <meta head-key="twitter:description" name="twitter:description" :content="post.meta_description ?? post.excerpt ?? `Berita dari ${organization.name}.`" />
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
            <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
                <Link href="/berita" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                    <AppIcon name="arrow_back" />
                    Kembali ke daftar berita
                </Link>

                <header class="mt-6">
                    <p class="text-sm font-semibold uppercase tracking-wider text-on-surface-variant">
                        {{ formatDateTime(post.published_at) }}
                        <span v-if="post.author_name"> · {{ post.author_name }}</span>
                    </p>
                    <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-tight text-primary sm:text-4xl">
                        {{ post.title }}
                    </h1>
                    <p v-if="post.excerpt" class="mt-4 text-lg leading-relaxed text-on-surface-variant">
                        {{ post.excerpt }}
                    </p>
                </header>

                <figure v-if="post.cover_image_url" class="mt-8 overflow-hidden rounded-2xl border border-outline-variant/40 shadow-md">
                    <img :src="post.cover_image_url" :alt="post.title" class="w-full object-cover">
                </figure>

                <!-- Content authored by tenant admins through the rich editor. -->
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div
                    class="prose-siupk mt-8"
                    v-html="post.content"
                />

                <footer class="mt-10 border-t border-outline-variant/60 pt-6">
                    <Link href="/berita" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary-container px-5 text-sm font-semibold text-on-primary-container hover:bg-primary/20">
                        <AppIcon name="newspaper" />
                        Lihat semua berita
                    </Link>
                </footer>
            </article>
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
