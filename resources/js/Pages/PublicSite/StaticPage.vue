<script setup>
import { Head } from '@inertiajs/vue3';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';

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
        <PublicSiteHeader :organization="organization" active="halaman" />

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
