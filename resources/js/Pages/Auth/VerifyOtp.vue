<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';

defineProps({
    maskedPhone: { type: String, required: true },
    resendsLeft: { type: Number, required: true },
});

const form = useForm({ otp: '' });
const flash = computed(() => usePage().props.flash);

function submit() {
    form.post(route('password.otp.verify'), {
        onFinish: () => form.reset(),
    });
}

function resend() {
    form.post(route('password.otp.resend'));
}
</script>

<template>
    <Head title="Verifikasi OTP - siupk Next" />

    <main class="flex min-h-screen items-center justify-center bg-surface p-6 font-sans text-on-surface sm:p-12">
        <div class="w-full max-w-md space-y-6">
            <header class="space-y-2 text-center">
                <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-primary-container text-on-primary shadow-md">
                    <AppIcon name="verified_user" class="text-3xl" />
                </div>
                <h1 class="text-3xl font-black tracking-tight text-primary">Verifikasi OTP</h1>
                <p class="text-sm text-on-surface-variant">
                    Masukkan 6 digit kode OTP yang dikirim ke <span class="font-bold text-primary">{{ maskedPhone }}</span>.
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
                        v-model="form.otp"
                        label="Kode OTP"
                        icon="pin"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        placeholder="000000"
                        required
                        autofocus
                        :error="form.errors.otp"
                    />

                    <AppButton
                        type="submit"
                        variant="success"
                        size="large"
                        class="w-full font-bold"
                        :loading="form.processing"
                        icon="check_circle"
                    >
                        Verifikasi OTP
                    </AppButton>

                    <div class="flex flex-col items-center justify-between gap-3 sm:flex-row">
                        <AppButton
                            type="button"
                            variant="secondary"
                            size="compact"
                            icon="refresh"
                            :disabled="resendsLeft === 0"
                            :loading="form.processing"
                            @click="resend"
                        >
                            Kirim Ulang
                        </AppButton>
                        <p class="text-sm text-on-surface-variant">
                            Sisa kirim ulang: <span class="font-bold text-primary">{{ resendsLeft }}</span>
                        </p>
                    </div>

                    <Link
                        :href="route('password.request')"
                        class="inline-flex items-center justify-center gap-1.5 text-sm font-bold text-primary hover:underline"
                    >
                        <AppIcon name="arrow_back" class="text-base" />
                        Ubah nomor atau minta kode baru
                    </Link>
                </form>
            </AppCard>
        </div>
    </main>
</template>
