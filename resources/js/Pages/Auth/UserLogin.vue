<script setup>
import { useI18n } from '../../i18n';
const { t } = useI18n();
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppLanguageSwitcher from '../../Components/AppLanguageSwitcher.vue';

const props = defineProps({
    scope: { type: String, default: 'tenant' },
    context: { type: Object, required: true },
    users: { type: Array, default: () => [] },
});

const search = ref('');
const loggingInId = ref(null);
const notice = ref('');

const isCentral = computed(() => props.scope === 'central');

const filteredUsers = computed(() => {
    const term = search.value.trim().toLowerCase();
    if (!term) return props.users;

    return props.users.filter((user) =>
        [user.name, user.username, user.email, user.role_name]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term)),
    );
});

const activeCount = computed(() => props.users.filter((user) => user.can_login).length);

function initials(name) {
    const parts = String(name ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

    if (parts.length === 0) return '?';

    return parts.map((part) => part.charAt(0).toUpperCase()).join('');
}

function avatarTone(index) {
    const tones = [
        'bg-primary-container text-on-primary-container',
        'bg-secondary-container text-on-secondary-container',
        'bg-tertiary-fixed text-on-tertiary',
        'bg-primary/15 text-primary',
        'bg-secondary/15 text-secondary',
        'bg-tertiary/15 text-tertiary',
    ];

    return tones[index % tones.length];
}

function statusLabel(user) {
    if (user.can_login) return t('Aktif');
    if (user.status !== 'active') return user.status === 'suspended' ? t('Ditangguhkan') : t('Nonaktif');

    return t('Tanpa keanggotaan');
}

function statusClass(user) {
    if (user.can_login) return 'bg-secondary-container/60 text-secondary';
    if (user.status !== 'active') return 'bg-error-container/50 text-error';

    return 'bg-tertiary-fixed/60 text-tertiary';
}

function login(user) {
    if (!user.can_login || loggingInId.value !== null) {
        if (!user.can_login) {
            notice.value = t('Akun :name tidak dapat digunakan untuk login.', { name: user.name });
        }

        return;
    }

    loggingInId.value = user.row_id;
    notice.value = '';

    const routeName = isCentral.value ? 'login.users.login' : 'login.tenant-users.login';
    const params = { user: user.row_id };

    router.post(route(routeName, params), {}, {
        preserveScroll: true,
        onFinish: () => {
            loggingInId.value = null;
        },
        onError: () => {
            notice.value = t('Gagal melakukan login otomatis. Coba lagi.');
        },
    });
}
</script>

<template>
    <Head :title="`${isCentral ? t('Pilih Akun Pusat') : t('Pilih Akun Login')} - siupk Next`" />

    <main class="min-h-screen bg-surface font-sans text-on-surface">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 py-6 sm:px-6 sm:py-10">
            <header class="flex flex-wrap items-center justify-between gap-4">
                <Link href="/" class="flex min-w-0 items-center gap-3 transition hover:opacity-90">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary text-on-primary shadow-sm">
                        <AppIcon name="account_balance" class="text-2xl" />
                    </span>
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-base font-bold tracking-tight">
                            siupk <span class="text-secondary">{{ t('Next') }}</span>
                        </span>
                        <span class="block truncate text-[11px] font-medium uppercase tracking-[0.18em] text-outline">
                            {{ context.name }}
                        </span>
                    </span>
                </Link>

                <div class="flex items-center gap-2">
                    <AppLanguageSwitcher align="right" tone="surface" />
                    <Link
                        :href="route('login')"
                        class="inline-flex items-center gap-2 rounded-full border border-outline-variant bg-surface-container-lowest px-4 py-2 text-sm font-semibold text-primary shadow-sm transition hover:bg-primary-container/40"
                    >
                        <AppIcon name="key" class="text-lg" />
                        {{ t('Login manual') }}
                    </Link>
                </div>
            </header>

            <section class="mt-8 space-y-2 sm:mt-12">
                <span class="inline-flex items-center gap-2 rounded-full bg-primary-container/40 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                    <span class="relative flex size-1.5">
                        <span class="absolute inset-0 rounded-full bg-primary opacity-75" />
                        <span class="relative inline-flex size-1.5 rounded-full bg-primary" />
                    </span>
                    {{ context.label }} &middot; {{ context.code }} &middot; {{ context.host }}
                </span>
                <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ isCentral ? t('Pilih Akun Pusat') : t('Pilih Akun Login') }}
                </h1>
                <p class="max-w-2xl text-sm text-on-surface-variant">
                    {{ t('Klik dua kali pada kartu pengguna untuk langsung masuk tanpa mengetik password.') }}
                    <template v-if="isCentral">
                        {{ t('Daftar ini hanya memuat akun platform pusat (superadmin, supervisor provinsi, supervisor kabupaten), bukan akun tenant lokasi.') }}
                    </template>
                    <template v-else>
                        {{ t('Daftar ini hanya memuat pengguna milik tenant') }}
                        <strong class="font-semibold text-on-surface">{{ context.name }}</strong>{{ t(', bukan akun pusat.') }}
                    </template>
                </p>
            </section>

            <section class="mt-6 flex flex-wrap items-center gap-3">
                <label class="relative min-w-0 flex-1 sm:max-w-sm">
                    <span class="sr-only">{{ t('Cari pengguna') }}</span>
                    <AppIcon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-outline" />
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="t('Cari pengguna...')"
                        class="w-full rounded-xl border border-outline-variant bg-surface-container-lowest py-2.5 pl-10 pr-3 text-sm text-on-surface shadow-sm outline-none transition placeholder:text-outline focus:border-primary focus:ring-2 focus:ring-primary/20"
                    >
                </label>

                <div class="flex items-center gap-2 text-xs font-medium text-on-surface-variant">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-secondary-container/50 px-3 py-1.5 text-secondary">
                        <span class="size-1.5 rounded-full bg-secondary" />
                        {{ activeCount }} {{ t('akun siap dipakai') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-container-low px-3 py-1.5">
                        <AppIcon name="group" class="text-sm" />
                        {{ users.length }} {{ t('total pengguna') }}
                    </span>
                </div>
            </section>

            <p
                v-if="notice"
                class="mt-4 flex items-start gap-2 rounded-xl border border-tertiary/30 bg-tertiary-fixed/30 p-3 text-sm text-on-surface"
                role="status"
            >
                <AppIcon name="info" class="mt-0.5 text-lg text-tertiary" />
                <span>{{ notice }}</span>
            </p>

            <section class="mt-6 flex-1">
                <div
                    v-if="filteredUsers.length > 0"
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <article
                        v-for="(user, index) in filteredUsers"
                        :key="user.row_id"
                        role="button"
                        :tabindex="user.can_login ? 0 : -1"
                        :aria-disabled="!user.can_login"
                        :aria-busy="loggingInId === user.row_id"
                        class="group relative flex flex-col gap-4 rounded-2xl border bg-surface-container-lowest p-4 text-left shadow-sm transition duration-200"
                        :class="[
                            user.can_login
                                ? 'cursor-pointer border-outline-variant/70 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40'
                                : 'cursor-not-allowed border-outline-variant/40 opacity-60',
                            loggingInId === user.row_id ? 'border-primary/60 ring-2 ring-primary/20' : '',
                        ]"
                        @dblclick="login(user)"
                        @keydown.enter.prevent="login(user)"
                        @keydown.space.prevent="login(user)"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-2xl text-sm font-black uppercase tracking-wide"
                                :class="avatarTone(index)"
                            >
                                <img
                                    v-if="user.photo_url"
                                    :src="user.photo_url"
                                    :alt="user.name"
                                    class="size-full object-cover"
                                >
                                <template v-else>{{ initials(user.name) }}</template>
                            </span>

                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-sm font-bold text-on-surface" :title="user.name">
                                    {{ user.name }}
                                </h2>
                                <p class="truncate text-xs font-medium text-primary">@{{ user.username }}</p>
                                <p v-if="user.email" class="mt-0.5 truncate text-xs text-on-surface-variant" :title="user.email">
                                    {{ user.email }}
                                </p>
                            </div>

                            <span
                                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide"
                                :class="statusClass(user)"
                            >
                                <span class="size-1.5 rounded-full bg-current" />
                                {{ statusLabel(user) }}
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-flex items-center gap-1 rounded-lg bg-surface-container-low px-2 py-1 text-[11px] font-semibold text-on-surface-variant">
                                <AppIcon name="badge" class="text-sm" />
                                {{ user.role_name || t('Tanpa role') }}
                            </span>
                            <span
                                v-if="user.is_village_user"
                                class="inline-flex items-center gap-1 rounded-lg bg-primary-container/40 px-2 py-1 text-[11px] font-semibold text-primary"
                            >
                                <AppIcon name="location_on" class="text-sm" />
                                {{ t('Operator desa') }}
                            </span>
                            <span
                                v-if="user.is_self"
                                class="inline-flex items-center gap-1 rounded-lg bg-secondary-container/60 px-2 py-1 text-[11px] font-semibold text-secondary"
                            >
                                <AppIcon name="check_circle" class="text-sm" />
                                {{ t('Sesi ini') }}
                            </span>
                        </div>

                        <div class="mt-auto flex items-center justify-between gap-2 border-t border-outline-variant/40 pt-3">
                            <span class="inline-flex items-center gap-1.5 text-[11px] text-outline">
                                <AppIcon name="schedule" class="text-sm" />
                                {{ user.last_login_at ? `${t('Login terakhir')} ${user.last_login_at}` : t('Belum pernah login') }}
                            </span>

                            <span
                                v-if="loggingInId === user.row_id"
                                class="inline-flex items-center gap-1.5 text-[11px] font-bold text-primary"
                            >
                                <AppIcon name="progress_activity" class="animate-spin text-sm" />
                                {{ t('Masuk...') }}
                            </span>
                            <span
                                v-else-if="user.can_login"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-primary opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100"
                            >
                                <AppIcon name="ads_click" class="text-sm" />
                                {{ t('Klik 2x') }}
                            </span>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="grid place-items-center rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-16 text-center"
                >
                    <span class="grid size-14 place-items-center rounded-2xl bg-surface-container-low text-outline">
                        <AppIcon name="person_search" class="text-3xl" />
                    </span>
                    <h2 class="mt-4 text-base font-bold">{{ t('Tidak ada pengguna yang cocok') }}</h2>
                    <p class="mt-1 max-w-sm text-sm text-on-surface-variant">
                        {{ search ? t('Coba kata kunci lain atau hapus pencarian.') : (isCentral ? t('Belum ada pengguna pusat yang aktif.') : t('Belum ada pengguna aktif pada tenant ini.')) }}
                    </p>
                </div>
            </section>

            <footer class="mt-8 flex flex-wrap items-center justify-between gap-2 border-t border-outline-variant/40 pt-4 text-[11px] text-outline">
                <span>&copy; {{ new Date().getFullYear() }} siupk Next &mdash; {{ context.name }}</span>
                <span>{{ t('Halaman bantuan demo — jangan gunakan pada instalasi produksi.') }}</span>
            </footer>
        </div>
    </main>
</template>
