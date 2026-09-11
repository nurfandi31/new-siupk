<script setup>
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';

const form = useForm({ identifier: '' });
const { props: flash } = usePage();

function submit() {
    form.post(route('password.otp.send'), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Lupa Password - siupk Next" />

    <main class="flex min-h-screen items-center justify-center bg-surface p-6 font-sans text-on-surface sm:p-12">
        <div class="w-full max-w-md space-y-6">
            <header class="space-y-2 text-center">
                <Link
                    href="/"
                    class="mx-auto grid size-12 place-items-center rounded-2xl bg-primary-container text-on-primary shadow-md"
                    aria-label="Kembali ke beranda"
                >
                    <AppIcon name="account_balance" class="text-3xl" />
                </Link>
                <h1 class="text-3xl font-black tracking-tight text-primary">Lupa Password</h1>
                <p class="text-sm text-on-surface-variant">
                    Masukkan username, email, atau nomor WhatsApp terdaftar untuk menerima kode OTP.
                </p>
            </header>

            <AppCard>
                <form class="space-y-6" @submit.prevent="submit">
                    <div
                        v-if="flash.info"
                        class="rounded-xl border border-primary/20 bg-primary-container/20 p-4 text-sm font-semibold text-primary"
                        role="status"
                    >
                        {{ flash.info }}
                    </div>

                    <AppInput
                        v-model="form.identifier"
                        label="Username, Email, atau No. WhatsApp"
                        icon="person"
                        autocomplete="username"
                        placeholder="Contoh: 081234567890"
                        required
                        autofocus
                        :error="form.errors.identifier"
                    />

                    <AppButton
                        type="submit"
                        variant="success"
                        size="large"
                        class="w-full font-bold"
                        :loading="form.processing"
                        icon="send"
                    >
                        Kirim Kode OTP
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
