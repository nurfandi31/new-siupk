<script setup>
import { computed } from 'vue';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';

const props = defineProps({
    grantToken: { type: String, required: true },
});

const form = useForm({
    grant_token: props.grantToken,
    password: '',
    password_confirmation: '',
});
const flash = computed(() => usePage().props.flash);

function submit() {
    form.post(route('password.reset'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Reset Password - siupk Next" />

    <main class="flex min-h-screen items-center justify-center bg-surface p-6 font-sans text-on-surface sm:p-12">
        <div class="w-full max-w-md space-y-6">
            <header class="space-y-2 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-primary-container text-on-primary shadow-md">
                    <AppIcon name="lock_reset" class="text-3xl" />
                </div>
                <h1 class="text-3xl font-black tracking-tight text-primary">Buat Password Baru</h1>
                <p class="text-sm text-on-surface-variant">Gunakan password baru yang kuat dan belum pernah dipakai sebelumnya.</p>
            </header>

            <AppCard>
                <form class="space-y-6" @submit.prevent="submit">
                    <div
                        v-if="flash.error"
                        class="rounded-xl border border-error/20 bg-error-container/30 p-4 text-sm font-semibold text-error"
                        role="alert"
                    >
                        {{ flash.error }}
                    </div>
                    <input v-model="form.grant_token" type="hidden" name="grant_token">

                    <AppInput
                        v-model="form.password"
                        label="Password Baru"
                        icon="lock"
                        type="password"
                        autocomplete="new-password"
                        placeholder="••••••••"
                        required
                        autofocus
                        :error="form.errors.password"
                    />

                    <AppInput
                        v-model="form.password_confirmation"
                        label="Konfirmasi Password Baru"
                        icon="lock"
                        type="password"
                        autocomplete="new-password"
                        placeholder="••••••••"
                        required
                        :error="form.errors.password_confirmation"
                    />

                    <AppButton
                        type="submit"
                        variant="success"
                        size="large"
                        class="w-full font-bold"
                        :loading="form.processing"
                        icon="save"
                    >
                        Simpan Password Baru
                    </AppButton>

                    <Link
                        :href="route('login')"
                        class="inline-flex items-center justify-center gap-1.5 text-sm font-bold text-primary hover:underline"
                    >
                        <AppIcon name="arrow_back" class="text-base" />
                        Kembali ke halaman masuk
                    </Link>
                </form>
            </AppCard>
        </div>
    </main>
</template>
