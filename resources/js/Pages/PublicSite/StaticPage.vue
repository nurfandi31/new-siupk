<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
    page: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`${page.title} — ${organization.name}`">
        <meta head-key="description" name="description" :content="page.meta_description ?? `Halaman ${page.title} dari ${organization.name}.`" />
        <meta head-key="og:title" property="og:title" :content="page.title" />
        <meta head-key="og:description" property="og:description" :content="page.meta_description ?? `Halaman ${page.title} dari ${organization.name}.`" />
        <meta head-key="og:type" property="og:type" content="article" />
        <meta head-key="og:url" property="og:url" :content="$page.url" />
        <meta head-key="twitter:card" name="twitter:card" content="summary" />
        <meta head-key="twitter:title" name="twitter:title" :content="page.title" />
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
                <header>
                    <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-primary sm:text-4xl">
                        {{ page.title }}
                    </h1>
                </header>

                <!-- Content authored by tenant admins through the rich editor. -->
                <!-- eslint-disable-next-line vue/no-v-html -->
                <div
                    class="prose-siupk mt-8"
                    v-html="page.content"
                />
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
