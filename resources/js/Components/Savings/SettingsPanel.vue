<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppBadge from '../AppBadge.vue';
import AppButton from '../AppButton.vue';
import AppCard from '../AppCard.vue';
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
    module: { type: Object, default: () => ({}) },
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

const tableRows = computed(() => {
    const labels = props.module.table_labels ?? {};
    const counts = props.module.row_counts ?? {};

    return Object.entries(props.module.tables ?? {}).map(([key, table]) => ({
        key,
        label: labels[key] ?? key,
        table,
        count: counts[key],
    }));
});

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
    <div class="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="min-w-0 space-y-6">
            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <AppIcon name="percent" tone="primary" />
                        <span class="font-semibold text-on-surface">{{ t('Ketentuan Bunga & Pajak') }}</span>
                    </div>
                </template>

                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput
                            v-model="settingsForm.interest_rate"
                            type="number"
                            step="0.001"
                            min="0"
                            max="100"
                            :label="t('Bunga Default (% / bulan)')"
                            icon="percent"
                            :error="settingsForm.errors.interest_rate"
                        />
                        <AppInput
                            v-model="settingsForm.tax_rate"
                            type="number"
                            step="0.001"
                            min="0"
                            max="100"
                            :label="t('Pajak Bunga (%)')"
                            icon="receipt_long"
                            :error="settingsForm.errors.tax_rate"
                        />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppCurrencyInput
                            v-model="settingsForm.admin_fee"
                            :label="t('Biaya Admin Default / Bulan')"
                            icon="payments"
                            :error="settingsForm.errors.admin_fee"
                        />
                        <AppCurrencyInput
                            v-model="settingsForm.opening_fee"
                            :label="t('Biaya Buka Buku Default')"
                            icon="receipt"
                            :error="settingsForm.errors.opening_fee"
                            :hint="t('Dipotong dari setoran awal sebagai mutasi tersendiri.')"
                        />
                        <AppCurrencyInput
                            v-model="settingsForm.minimum_balance"
                            :label="t('Minimum Saldo Bunga (min_bunga)')"
                            icon="account_balance_wallet"
                            :error="settingsForm.errors.minimum_balance"
                            :hint="t('Saldo di bawah nilai ini tidak mendapat bunga.')"
                        />
                        <AppCurrencyInput
                            v-model="settingsForm.minimum_taxable_interest"
                            :label="t('Minimum Bunga Kena Pajak')"
                            icon="gavel"
                            :error="settingsForm.errors.minimum_taxable_interest"
                            :hint="t('Bunga di bawah nilai ini tidak dipotong pajak.')"
                        />
                        <AppCurrencyInput
                            v-model="settingsForm.minimum_withdrawal_balance"
                            :label="t('Saldo Minimal Penarikan Default')"
                            icon="savings"
                            :error="settingsForm.errors.minimum_withdrawal_balance"
                            :hint="t('Dipakai saat membuat jenis simpanan baru.')"
                        />
                        <AppInput
                            v-model="settingsForm.interest_day"
                            type="number"
                            min="-28"
                            max="28"
                            :label="t('Tanggal Bunga (tgl_bunga)')"
                            icon="calendar_month"
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
            </AppCard>

            <AppCard :padded="false" class="min-w-0">
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <AppIcon name="category" tone="primary" />
                            <span class="font-semibold text-on-surface">{{ t('Jenis Simpanan') }}</span>
                        </div>
                        <AppButton variant="secondary" icon="add" size="compact" @click="openCreateProduct">
                            {{ t('Tambah Jenis') }}
                        </AppButton>
                    </div>
                </template>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-outline-variant text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Kode') }}</th>
                                <th class="px-4 py-3 text-left font-semibold">{{ t('Nama') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Bunga') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Pajak') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Admin') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Saldo Min') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Rekening') }}</th>
                                <th class="px-4 py-3 text-right font-semibold">{{ t('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            <tr v-for="product in products" :key="product.row_id" class="transition hover:bg-surface-container-low/60">
                                <td class="px-4 py-3 font-mono text-xs font-bold text-primary">{{ product.code }}</td>
                                <td class="px-4 py-3">
                                    <p class="whitespace-nowrap font-semibold text-on-surface">{{ product.name }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1">
                                        <AppBadge tone="primary-soft">{{ product.category_label }}</AppBadge>
                                        <AppBadge tone="neutral">{{ product.form_type_label }}</AppBadge>
                                        <AppBadge v-if="!product.is_active" tone="neutral">{{ t('Nonaktif') }}</AppBadge>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-on-surface">{{ product.interest_rate }}%</td>
                                <td class="px-4 py-3 text-right text-on-surface">{{ product.tax_rate }}%</td>
                                <td class="px-4 py-3 text-right text-on-surface">{{ money(product.admin_fee) }}</td>
                                <td class="px-4 py-3 text-right text-on-surface">{{ money(product.minimum_balance) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <AppBadge tone="neutral">{{ product.accounts_count }}</AppBadge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="flex justify-end gap-1">
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
                                </td>
                            </tr>
                            <tr v-if="products.length === 0">
                                <td colspan="8" class="px-4 py-10 text-center text-on-surface-variant">
                                    {{ t('Belum ada jenis simpanan. Tambahkan minimal satu jenis.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>

        <aside class="min-w-0 space-y-4">
            <AppCard class="border border-outline-variant bg-gradient-to-br from-secondary-container/50 to-surface-container-low">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-2xl bg-surface/80 text-secondary">
                        <AppIcon name="database" />
                    </span>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-on-surface-variant">{{ t('Tabel Modul') }}</p>
                        <p class="font-semibold text-on-surface">{{ t('Khusus lokasi ini') }}</p>
                    </div>
                </div>
                <div class="mt-4 space-y-2 text-sm">
                    <div v-for="row in tableRows" :key="row.key" class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-on-surface">{{ t(row.label) }}</p>
                            <p class="truncate font-mono text-[11px] text-on-surface-variant">{{ row.table }}</p>
                        </div>
                        <AppBadge :tone="row.count > 0 ? 'success-soft' : 'neutral'">{{ row.count ?? 0 }}</AppBadge>
                    </div>
                </div>
                <p class="mt-4 rounded-xl bg-surface/70 p-3 text-xs text-on-surface-variant">
                    {{ t('Tabel berawalan') }} <span class="font-mono font-semibold">simp_</span>
                    {{ t('dibuat khusus untuk modul ini dan tidak dipakai lokasi lain.') }}
                </p>
            </AppCard>

            <AppCard>
                <template #header>
                    <div class="flex items-center gap-2">
                        <AppIcon name="info" tone="primary" />
                        <span class="font-semibold text-on-surface">{{ t('Informasi') }}</span>
                    </div>
                </template>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-on-surface-variant">{{ t('Status modul') }}</dt>
                        <dd>
                            <AppBadge :tone="module.is_enabled ? 'success-soft' : 'neutral'">
                                {{ module.is_enabled ? t('Aktif') : t('Nonaktif') }}
                            </AppBadge>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-on-surface-variant">{{ t('Diaktifkan') }}</dt>
                        <dd class="text-right font-medium text-on-surface">{{ module.enabled_at || '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-on-surface-variant">{{ t('Total baris') }}</dt>
                        <dd class="font-medium text-on-surface">{{ module.total_rows ?? 0 }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-on-surface-variant">
                    {{ t('Penghapusan modul hanya dapat dilakukan dari pusat tenant dan ditolak bila tabel masih berisi data.') }}
                </p>
            </AppCard>

            <AppCard class="border border-outline-variant bg-surface-container-low/60">
                <p class="text-xs font-bold uppercase tracking-wide text-on-surface-variant">{{ t('Pemetaan SOP SIUPK') }}</p>
                <ul class="mt-2 space-y-1 text-xs text-on-surface-variant">
                    <li>• Bunga &amp; pajak adalah persen per bulan.</li>
                    <li>• Biaya buka buku dipotong dari setoran awal.</li>
                    <li>• Bunga memakai metode saldo sesuai hitung_bunga.</li>
                    <li>• Admin ditagih setiap periode meski bunga nol.</li>
                    <li>• Penarikan dibatasi saldo minimal jenis simpanan.</li>
                </ul>
            </AppCard>
        </aside>
    </div>

    <AppModal v-model="productOpen" :title="editingProduct ? t('Edit Jenis Simpanan') : t('Tambah Jenis Simpanan')" size="md">
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
