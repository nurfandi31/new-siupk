<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppBadge from '../AppBadge.vue';
import AppButton from '../AppButton.vue';
import AppCurrencyInput from '../AppCurrencyInput.vue';
import AppIcon from '../AppIcon.vue';
import AppIconButton from '../AppIconButton.vue';
import AppInput from '../AppInput.vue';
import AppModal from '../AppModal.vue';
import AppSwitch from '../AppSwitch.vue';
import AppTextarea from '../AppTextarea.vue';
import SmartSelect from '../SmartSelect.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useMoney } from '../../composables/useMoney';
import { useI18n } from '../../i18n';

const { t } = useI18n();
const { money } = useMoney();
const { confirm } = useConfirm();

const props = defineProps({
    settings: { type: Object, required: true },
    products: { type: Array, default: () => [] },
    methods: { type: Array, default: () => [] },
    categories: { type: Object, default: () => ({}) },
    formTypes: { type: Object, default: () => ({}) },
});

const settingsForm = useForm({
    interest_rate: props.settings.interest_rate,
    tax_rate: props.settings.tax_rate,
    admin_fee: props.settings.admin_fee,
    opening_fee: props.settings.opening_fee,
    interest_day: props.settings.interest_day,
    minimum_balance: props.settings.minimum_balance,
    minimum_taxable_interest: props.settings.minimum_taxable_interest,
    minimum_withdrawal_balance: props.settings.minimum_withdrawal_balance ?? 20000,
    interest_method: props.settings.interest_method,
    allow_negative_balance: props.settings.allow_negative_balance,
});

const productOpen = ref(false);
const editingProduct = ref(null);

const productForm = useForm({
    code: '',
    name: '',
    category: 'umum',
    form_type: 1,
    interest_rate: 0.18,
    tax_rate: 1,
    admin_fee: 0,
    opening_fee: 5000,
    minimum_balance: 20000,
    is_active: true,
    sort_order: 0,
    description: '',
});

const categoryOptions = computed(() =>
    Object.entries(props.categories ?? {}).map(([value, label]) => ({ value, label })),
);

const formTypeOptions = computed(() =>
    Object.entries(props.formTypes ?? {}).map(([value, label]) => ({ value: Number(value), label })),
);

const methodOptions = computed(() =>
    props.methods.map((method) => ({ value: method.value, label: t(method.label) })),
);

function saveSettings() {
    settingsForm.put('/simpanan/pengaturan', { preserveScroll: true });
}

function openCreateProduct() {
    editingProduct.value = null;
    productForm.clearErrors();
    productForm.code = '';
    productForm.name = '';
    productForm.category = 'umum';
    productForm.form_type = 1;
    productForm.interest_rate = props.settings.interest_rate;
    productForm.tax_rate = props.settings.tax_rate;
    productForm.admin_fee = props.settings.admin_fee;
    productForm.opening_fee = props.settings.opening_fee;
    productForm.minimum_balance = props.settings.minimum_withdrawal_balance ?? 20000;
    productForm.is_active = true;
    productForm.sort_order = props.products.length + 1;
    productForm.description = '';
    productOpen.value = true;
}

function openEditProduct(product) {
    editingProduct.value = product;
    productForm.clearErrors();
    productForm.code = product.code;
    productForm.name = product.name;
    productForm.category = product.category;
    productForm.form_type = product.form_type ?? 1;
    productForm.interest_rate = product.interest_rate;
    productForm.tax_rate = product.tax_rate;
    productForm.admin_fee = product.admin_fee;
    productForm.opening_fee = product.opening_fee;
    productForm.minimum_balance = product.minimum_balance;
    productForm.is_active = product.is_active;
    productForm.sort_order = product.sort_order;
    productForm.description = product.description ?? '';
    productOpen.value = true;
}

function submitProduct() {
    if (editingProduct.value) {
        productForm.put(`/simpanan/pengaturan/jenis/${editingProduct.value.row_id}`, {
            preserveScroll: true,
            onSuccess: () => {
                productOpen.value = false;
            },
        });

        return;
    }

    productForm.post('/simpanan/pengaturan/jenis', {
        preserveScroll: true,
        onSuccess: () => {
            productOpen.value = false;
        },
    });
}

async function deleteProduct(product) {
    const ok = await confirm({
        title: t('Hapus Jenis Simpanan?'),
        message: t('Jenis simpanan hanya dapat dihapus bila belum dipakai rekening mana pun.'),
        confirmLabel: t('Hapus'),
        variant: 'danger',
    });
    if (!ok) return;

    router.delete(`/simpanan/pengaturan/jenis/${product.row_id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="min-w-0 divide-y divide-outline-variant">
        <section class="space-y-4 pb-6">
            <div class="flex items-center gap-2">
                <AppIcon name="percent" tone="primary" :container-size="8" />
                <h3 class="text-sm font-bold text-primary">{{ t('Ketentuan Bunga & Pajak') }}</h3>
            </div>

            <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput
                            v-model="settingsForm.interest_rate"
                            type="number"
                            step="0.001"
                            min="0"
                            max="100"
                            :label="t('Bunga Default (% / bulan)')"
                            :error="settingsForm.errors.interest_rate"
                        />
                        <AppInput
                            v-model="settingsForm.tax_rate"
                            type="number"
                            step="0.001"
                            min="0"
                            max="100"
                            :label="t('Pajak Bunga (%)')"
                            :error="settingsForm.errors.tax_rate"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppCurrencyInput
                            v-model="settingsForm.admin_fee"
                            :label="t('Biaya Admin Default / Bulan')"
                            :error="settingsForm.errors.admin_fee"
                        />
                        <AppCurrencyInput
                            v-model="settingsForm.opening_fee"
                            :label="t('Biaya Buka Buku Default')"
                            :error="settingsForm.errors.opening_fee"
                            :hint="t('Dipotong dari setoran awal sebagai mutasi tersendiri.')"
                        />
                        <AppCurrencyInput
                            v-model="settingsForm.minimum_balance"
                            :label="t('Minimum Saldo Bunga (min_bunga)')"
                            :error="settingsForm.errors.minimum_balance"
                            :hint="t('Saldo di bawah nilai ini tidak mendapat bunga.')"
                        />
                        <AppCurrencyInput
                            v-model="settingsForm.minimum_taxable_interest"
                            :label="t('Minimum Bunga Kena Pajak')"
                            :error="settingsForm.errors.minimum_taxable_interest"
                            :hint="t('Bunga di bawah nilai ini tidak dipotong pajak.')"
                        />
                        <AppCurrencyInput
                            v-model="settingsForm.minimum_withdrawal_balance"
                            :label="t('Saldo Minimal Penarikan Default')"
                            :error="settingsForm.errors.minimum_withdrawal_balance"
                            :hint="t('Dipakai saat membuat jenis simpanan baru.')"
                        />
                        <AppInput
                            v-model="settingsForm.interest_day"
                            type="number"
                            min="-28"
                            max="28"
                            :label="t('Tanggal Bunga (tgl_bunga)')"
                            :error="settingsForm.errors.interest_day"
                            :hint="t('0 = tanggal 1. Negatif dihitung dari akhir bulan, mis. -1 = akhir bulan.')"
                        />
                    </div>

                    <SmartSelect
                        v-model="settingsForm.interest_method"
                        :label="t('Metode Dasar Bunga (hitung_bunga)')"
                        :options="methodOptions"
                        :error="settingsForm.errors.interest_method"
                        hint="1 = saldo terakhir, 2 = saldo terendah, 3 = saldo rata-rata harian. Sama dengan SIUPK."
                    />

                    <div class="flex items-center justify-between gap-4 rounded-xl bg-surface-container-low p-4">
                        <div>
                            <p class="font-semibold text-on-surface">{{ t('Izinkan saldo negatif') }}</p>
                            <p class="text-sm text-on-surface-variant">
                                {{ t('SIUPK menagih admin penuh setiap periode. Jika aktif, penarikan melebihi saldo tetap dicatat. Disarankan nonaktif.') }}
                            </p>
                        </div>
                        <AppSwitch v-model="settingsForm.allow_negative_balance" />
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-outline-variant pt-4">
                        <p class="text-xs text-on-surface-variant">
                            {{ t('Bunga, pajak, dan biaya berlaku untuk seluruh rekening di lokasi ini.') }}
                        </p>
                        <AppButton variant="primary" icon="save" :loading="settingsForm.processing" :disabled="settingsForm.processing" @click="saveSettings">
                            {{ t('Simpan Pengaturan') }}
                        </AppButton>
                    </div>
                </div>
        </section>

        <section class="space-y-4 pt-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <AppIcon name="category" tone="primary" :container-size="8" />
                    <h3 class="text-sm font-bold text-primary">{{ t('Jenis Simpanan') }}</h3>
                </div>
                <AppButton variant="secondary" icon="add" size="compact" @click="openCreateProduct">
                    {{ t('Tambah Jenis') }}
                </AppButton>
            </div>

            <div v-if="products.length === 0" class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-low p-10 text-center">
                <AppIcon name="savings" class="text-5xl text-on-surface-variant" />
                <p class="mt-3 text-sm font-bold text-primary">{{ t('Belum ada jenis simpanan') }}</p>
                <p class="mt-1 text-xs text-on-surface-variant">{{ t('Tambahkan minimal satu jenis simpanan untuk mulai membuka rekening.') }}</p>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2">
                <article
                    v-for="product in products"
                    :key="product.row_id"
                    class="group relative flex min-w-0 flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest p-5 shadow-sm transition-all hover:border-primary-container hover:shadow-md"
                >
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary to-secondary opacity-0 transition-opacity group-hover:opacity-100" />

                    <div class="flex items-start gap-3">
                        <div class="grid size-11 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-primary to-primary-container text-on-primary shadow-md shadow-primary/20">
                            <AppIcon name="savings" class="text-xl" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-bold text-primary">{{ product.name }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                <code class="rounded bg-surface-container px-1.5 py-0.5 font-mono text-xs text-on-surface-variant">{{ product.code }}</code>
                                <AppBadge tone="primary-soft">{{ product.category_label }}</AppBadge>
                                <AppBadge tone="neutral">{{ product.form_type_label }}</AppBadge>
                                <AppBadge v-if="!product.is_active" tone="neutral">{{ t('Nonaktif') }}</AppBadge>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <AppIconButton
                                name="edit"
                                tone="primary"
                                size="md"
                                :tooltip="t('Edit jenis simpanan')"
                                @click="openEditProduct(product)"
                            />
                            <AppIconButton
                                name="delete"
                                tone="danger"
                                size="md"
                                :disabled="product.accounts_count > 0"
                                :tooltip="product.accounts_count > 0 ? t('Tidak dapat dihapus karena sudah dipakai rekening') : t('Hapus jenis simpanan')"
                                @click="deleteProduct(product)"
                            />
                        </div>
                    </div>

                    <p v-if="product.description" class="mt-3 line-clamp-2 text-sm text-on-surface-variant">{{ product.description }}</p>

                    <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-outline-variant/60 pt-4 text-sm">
                        <div>
                            <dt class="text-xs text-on-surface-variant">{{ t('Bunga / bulan') }}</dt>
                            <dd class="mt-0.5 font-semibold text-on-surface">{{ product.interest_rate }}%</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-on-surface-variant">{{ t('Pajak') }}</dt>
                            <dd class="mt-0.5 font-semibold text-on-surface">{{ product.tax_rate }}%</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-on-surface-variant">{{ t('Admin / bulan') }}</dt>
                            <dd class="mt-0.5 font-semibold text-on-surface">{{ money(product.admin_fee) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-on-surface-variant">{{ t('Saldo minimum') }}</dt>
                            <dd class="mt-0.5 font-semibold text-on-surface">{{ money(product.minimum_balance) }}</dd>
                        </div>
                    </dl>

                    <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                        <span class="text-xs text-on-surface-variant">{{ t('Rekening terhubung') }}</span>
                        <AppBadge tone="neutral">{{ product.accounts_count }}</AppBadge>
                    </div>
                </article>
            </div>
        </section>
    </div>

    <AppModal v-model="productOpen" :title="editingProduct ? t('Edit Jenis Simpanan') : t('Tambah Jenis Simpanan')" size="md" centered>
        <form class="space-y-4" @submit.prevent="submitProduct">
            <div class="grid gap-4 sm:grid-cols-2">
                <AppInput
                    v-model="productForm.code"
                    :label="t('Kode')"
                    :error="productForm.errors.code"
                    placeholder="5"
                    :disabled="Boolean(editingProduct && editingProduct.accounts_count > 0)"
                    :hint="editingProduct && editingProduct.accounts_count > 0 ? t('Kode tidak dapat diubah karena sudah dipakai rekening.') : null"
                />
                <AppInput v-model="productForm.name" :label="t('Nama Jenis')" :error="productForm.errors.name" placeholder="Simpanan Pendidikan" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <SmartSelect v-model="productForm.category" :label="t('Kategori')" :options="categoryOptions" :error="productForm.errors.category" />
                <SmartSelect v-model="productForm.form_type" :label="t('Tipe Formulir')" :options="formTypeOptions" :error="productForm.errors.form_type" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <AppInput v-model="productForm.interest_rate" type="number" step="0.001" min="0" max="100" :label="t('Bunga (%)')" :error="productForm.errors.interest_rate" />
                <AppInput v-model="productForm.tax_rate" type="number" step="0.001" min="0" max="100" :label="t('Pajak (%)')" :error="productForm.errors.tax_rate" />
                <AppInput v-model="productForm.sort_order" type="number" min="0" :label="t('Urutan')" :error="productForm.errors.sort_order" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <AppCurrencyInput v-model="productForm.admin_fee" :label="t('Admin / Bulan')" :error="productForm.errors.admin_fee" />
                <AppCurrencyInput
                    v-model="productForm.opening_fee"
                    :label="t('Biaya Buka Buku')"
                    :error="productForm.errors.opening_fee"
                    :hint="t('Dipotong dari setoran awal.')"
                />
            </div>
            <AppCurrencyInput
                v-model="productForm.minimum_balance"
                :label="t('Saldo Minimal Penarikan')"
                :error="productForm.errors.minimum_balance"
                :hint="t('Saldo setelah penarikan tidak boleh kurang dari nilai ini (saldo_minimal).')"
            />
            <AppTextarea v-model="productForm.description" :label="t('Deskripsi')" :error="productForm.errors.description" :rows="2" />
            <div class="flex items-center justify-between gap-4 rounded-xl bg-surface-container-low p-3">
                <p class="text-sm font-semibold text-on-surface">{{ t('Aktif') }}</p>
                <AppSwitch v-model="productForm.is_active" />
            </div>
        </form>
        <template #footer>
            <AppButton variant="secondary" @click="productOpen = false">{{ t('Batal') }}</AppButton>
            <AppButton :loading="productForm.processing" icon="save" @click="submitProduct">{{ t('Simpan') }}</AppButton>
        </template>
    </AppModal>
</template>
