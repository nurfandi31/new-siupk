# AGENT.md — Pedoman Wajib untuk Coding UI

Project ini adalah **Laravel + Inertia.js + Vue 3 (Composition API) + Tailwind** dengan token **Material Design 3** (`surface-container-lowest`, `on-surface-variant`, `primary`, `error-container`, dst). Sebelum menulis satu baris pun markup atau styling UI, **WAJIB** mengikuti protokol di bawah ini. Aturan ini meng-override default behavior — patuhi persis seperti tertulis.

---

## Protokol 3-Langkah Sebelum Coding UI

Setiap kali tugas melibatkan **pembuatan, perubahan tampilan, atau perilaku komponen UI**, kerjakan langkah ini secara berurutan. **Jangan loncat ke langkah 3** sebelum langkah 1 & 2 selesai.

### Langkah 1 — Inventarisasi komponen yang tersedia

Jalankan dua Glob paralel dan baca hasilnya:

```
Glob: resources/js/Components/**/*.vue
Glob: resources/js/Layouts/*.vue
Glob: resources/js/composables/*.js
```

**Inventory wajib diketahui** (cek dulu apakah versi terbaru masih sama sebelum menggunakan — daftar ini bisa bergeser setiap rilis):

#### Komponen atomik (`App*` prefix)

| Komponen | Fungsi | Catatan API |
|---|---|---|
| `AppButton.vue` | Tombol | Props: `variant` (`primary`/`secondary`/`ghost`/`danger`/`success`), `size` (`compact`/`default`/`large`), `icon`, `iconOnly`, `loading` |
| `AppIconButton.vue` | Tombol icon-only | Props: `name`, `tone` (`neutral`/`primary`/`success`/`warning`/`danger`/`info`/...), `size` (`sm`/`md`/`lg`), `rounded` (`square`/`lg`/`full`), `filled`, `loading`, `tooltip`, `aria-label` |
| `AppCard.vue` | Kartu konten | Slot: `header` (opsional, sudah ada border-bottom + flex header), `default`; Props: `padded`, `bordered` |
| `AppInput.vue` | Input teks | `v-model`, `label` (wajib), `icon`, `error`, `hint`, `tooltip`, `hideLabel` |
| `AppTextarea.vue` | Textarea | Serupa `AppInput` |
| `AppCurrencyInput.vue` | Input mata uang | Pakai `useMoney()`; mendukung koma desimal opsional |
| `AppDatePicker.vue` | Date picker | `v-model` ISO string |
| `AppRadioGroup.vue` | Radio button group | `v-model` |
| `AppSwitch.vue` | Toggle on/off | `v-model` boolean (wajib `<AppSwitch v-model>` saja — bukan peer + class) |
| `AppCheckbox.vue` | Checkbox | `v-model` boolean atau array (`+ :value="x"`), `variant` (`cell`/`inline`/`field`), `disabled` |
| `AppBadge.vue` | Label status | Props: `tone` (`neutral`/`success`/`warning`/`error`/`primary` + `*-soft`) |
| `AppModal.vue` | Dialog/modal | `v-model:model-value` boolean, `size` (`sm`/`md`/`lg`/`full`), `title`, `closeable`; slot `footer` |
| `AppConfirmDialog.vue` | Modal konfirmasi | Dipakai via **`useConfirm()`** (lihat komposabel) |
| `AppEmptyState.vue` | State kosong | `title`, `description`, slot `icon`/`action` |
| `AppToast.vue` | Notifikasi toast | **Otomatis** lewat `page.props.flash` (Inertia) — tidak perlu komposabel |
| `AppTooltip.vue` | Tooltip | `text` |
| `AppIcon.vue` | Icon Material | `name` (string, mis. `"save"`, `"delete"`); Props tambahan `tone` untuk membungkus ikon dalam container berwarna MD3 (`success`→secondary-container, `warning`→tertiary, `danger`/`error`→error, `info`→primary-container, `primary`→primary-container, `neutral`→plain). Prop `containerSize` (default `9`) dan `containerShape` (`rounded`/`pill`) |
| `AppRichEditor.vue` | WYSIWYG | `v-model` HTML |

#### Komponen domain (inti tinggi, sering dipakai ulang)

| Komponen | Fungsi |
|---|---|
| `SmartDataTable.vue` | Tabel data dengan sort, filter, paginasi |
| `SmartSelect.vue` | Select dengan pencarian + remote option |
| `CsvImportExport.vue` | Upload CSV + export |
| `ReportPeriodFilter.vue` | Filter periode laporan |
| `LoanHistoryTable.vue` | Tabel histori pinjaman (domain Lending) |
| `TrendBarChart.vue` | Bar chart tren |
| `NotificationDropdown.vue` | Dropdown notifikasi di navbar |
| `ThemeMenu.vue` | Pilih tema (light/dark/system) |
| `AssistantWidget.vue` | Widget asisten AI mengambang |

#### Komposabel (`resources/js/composables/`)

- `useConfirm()` — konfirmasi modal destruktif/non-destruktif (`confirmState.variant: 'danger' | 'primary'`)
- `useMoney()` — format & parse mata uang Rupiah (pakai untuk nilai nominal, jangan format sendiri)
- `useTheme()` — baca/tulis tema aktif
- `useCan(permission)` — cek izin user (`v-if="can('...')"`)
- `useMarkdown()` — render markdown
- `usePeriodOptions()` — opsi periode laporan (bulan/tahun)

> **Catatan:** `useToast()` tersedia di `resources/js/composables/useToast.js` dan dikonsumsi `AppToast.vue`; composable ini juga menyediakan notifikasi programatis melalui method `success`, `error`, `warning`, dan `info`. Toast dari flash Inertia tetap jalan lewat watcher `page.props.flash` pada `AppToast.vue`.

#### Layout

- `AdminLayout.vue` — area admin (sidebar + topbar + `AssistantWidget`)
- `AuthenticatedLayout.vue` — generic authenticated page
- `RegencyLayout.vue` / `ProvinceLayout.vue` — layout sesuai tenant role

### Langkah 2 — Verifikasi kecocokan

Untuk setiap kebutuhan UI, bandingkan dengan inventory:

| Kebutuhan UI | Komponen yang harus dipakai |
|---|---|
| Tombol apa pun | `AppButton` |
| Tombol icon-only (lingkaran/persegi kecil di tabel) | `AppIconButton` (props: `name`, `tone`, `size`, `tooltip`) |
| Input teks/nomor/email/dll | `AppInput` (atau `AppTextarea`/`AppCurrencyInput`) |
| Konfirmasi "Yakin hapus?" | `useConfirm()` → render `<AppConfirmDialog />` sekali di root layout |
| Toast sukses/gagal | Flash Inertia lewat `page.props.flash` (lihat `AppToast.vue`), atau `useToast()` untuk notifikasi programatis dari event handler |
| Pilih tanggal | `AppDatePicker` |
| Toggle aktif/non-aktif | `AppSwitch` (boolean `v-model`) — atau `AppCheckbox :value="x" v-model="array"` untuk multi-select |
| Checkbox dalam tabel | `AppCheckbox` (`variant="cell"` default; `inline` untuk Login "remember me"; `field` untuk form-field sejajar AppInput) |
| Status badge/chip | `AppBadge :tone="..."` (tone: `success`/`warning`/`error`/`primary`/`neutral` + `*-soft`) |
| Ikon dengan warna semantik (stat-tile, status pill) | `<AppIcon name="x" tone="success" containerSize="9" containerShape="rounded" />` |
| Tabel data besar | `SmartDataTable` (jangan tulis `<table>` manual untuk data besar) |
| Pilih dari daftar panjang | `SmartSelect` |
| Periode laporan | `ReportPeriodFilter` |
| Format nominal | `useMoney()` — **dilarang** `toLocaleString` inline |
| Render markdown | `useMarkdown()` |
| Cek permission tombol | `v-if="can('permissions.x')"` |

### Langkah 3 — Baru bikin komponen baru **jika** memang tidak ada

Boleh membuat komponen baru hanya jika:

1. Inventarisasi sudah selesai dan kecocokan jelas **tidak ada**.
2. Komponen baru akan digunakan **≥ 2 kali** atau punya kompleksitas nyata (≥ ~50 baris).
3. Nama mengikuti konvensi: PascalCase, bernoun (`AppSuffix` untuk atomik, domain-named untuk feature).
4. Pakai `defineProps` + `defineModel` (Composition API `<script setup>`), `defineOptions({ inheritAttrs: false })` jika membungkus elemen root.
5. Slot composition (`#header`, `#footer`, `#default`), bukan prop string HTML.
6. Wajib ditambahkan ke **inventory Step 1** di update berikutnya — taruh di lokasi konvensi (`resources/js/Components/` untuk atomik, atau subfolder domain).

---

## Konvensi Tailwind & Token Material

Gunakan token MD3, **bukan** warna hard-coded:

| Token MD3 | Penggunaan |
|---|---|
| `bg-surface-container-lowest` | Latar konten |
| `bg-surface-container-low` / `bg-surface-container` | Elevated card, panel |
| `bg-primary`, `text-on-primary`, `bg-primary-container`, `text-on-primary-container` | Aksi primer |
| `bg-secondary`, `text-on-secondary` | Aksi sekunder/success |
| `bg-error`, `text-on-error`, `bg-error-container`, `text-on-error` | Destruktif / validasi gagal |
| `text-on-surface-variant` | Teks sekunder (label, hint) |
| `border-outline-variant` | Garis batas tipis |
| `card-shadow` | Utilitas shadow kartu (sudah ada di `tailwind.config.js`) |

Ukuran tombol pakai `min-h-10` / `min-h-12` / `min-h-14` (lihat `AppButton.sizes`).

**Dilarang** inline `style="color: #..."`, `bg-gray-500`, `text-red-600`, dsb. Selalu pakai token.

## Pola Vue 3 yang konsisten dengan codebase

- `<script setup>` dengan `defineModel`, `defineProps`, `defineEmits` — **bukan** `data()` Options API.
- `ref(null)` + `defineExpose` untuk kontrol fokus imperatif (lihat `AppButton`/`AppModal`).
- Komposabel di-import di **atas** script, sebelum pemakaian.
- Hindari `v-html` kecuali untuk markdown yang sudah disanitasi via `useMarkdown()`.
- Ikon **wajib** `AppIcon` — jangan pakai `<svg>` inline atau library lain.
- Halaman ada di `resources/js/Pages/<Domain>/<Resource>/<Action>.vue` dan di-render via Inertia (`Head` + `useForm` + `Link`).

---

## Anti-Contoh (Jangan Lakukan)

âŒ Tulis `<button class="px-4 py-2 bg-blue-500 text-white rounded">Simpan</button>` di halaman baru — pakailah `<AppButton variant="primary">Simpan</AppButton>`.

âŒ Tulis `<button class="grid size-9 place-items-center rounded-full ...">` untuk icon-only — pakailah `<AppIconButton name="x" tone="..." />`.

âŒ Tulis `<input type="checkbox" class="size-4 rounded border-outline-variant text-primary ...">` raw — pakailah `<AppCheckbox v-model="..." />`.

âŒ Tulis peer-switch UI manual `<label><input class="peer sr-only"> <span class="... peer-checked:bg-primary"></span> <span class="... peer-checked:translate-x-4"></span></label>` — pakailah `<AppSwitch v-model="..." />` (atau `<AppCheckbox>` untuk multi-select array).

âŒ Inline `<div class="bg-emerald-500/10 text-emerald-700"> ... </div>` untuk status pill — pakailah `<AppBadge tone="success"> ... </AppBadge>`. Untuk ikon + label, `<AppIcon tone="success" />`.

âŒ Hardcoded warna `bg-emerald-{500/50/100}`, `text-red-600`, `bg-amber-50`, `border-slate-300`, `bg-blue-500/10`, `text-gray-700`, dll. — selalu ganti dengan token MD3: `bg-secondary-container`, `text-secondary`, `bg-tertiary-fixed`, `border-outline-variant`, `bg-primary-container`, `text-on-surface-variant`, dsb. Tone mapping: emerald→secondary, amber→tertiary, red/rose→error, blue→primary/info, slate/gray→on-surface-variant.

âŒ `window.confirm("Yakin hapus?")` di halaman baru — pakailah `useConfirm()` + `AppConfirmDialog`.

âŒ `alert("Data tersimpan")` — pakai flash message dari controller (`session()->flash('flash.banner', '...')`), atau `<AppBadge tone="success">` inline.

âŒ Tabel `<table><thead>...` manual untuk data besar — pakailah `SmartDataTable`. (Untuk tabel ringkas/laporan, `<table>` manual masih boleh sepanjang pakai kelas token MD3.)

âŒ Format rupiah inline `Rp ${value.toLocaleString('id-ID')}` — pakailah `useMoney().format(value)`.

âŒ `if ($page.props.auth.user.permissions.includes('x'))` di template — pakailah `can('x')` dari `useCan`.

âŒ Bikin tombol confirm dengan `setTimeout` + custom state — pakailah `AppConfirmDialog`.

âŒ Taruh komponen baru di `resources/js/Pages/.../components/` — taruh di `resources/js/Components/` sesuai konvensi.

---

## Ringkasan Cepat

Sebelum nulis markup:

1. **Glob** `resources/js/Components/**/*.vue` + `composables/*.js` + cek kebutuhan di tabel Langkah 2.
2. **Pakai ulang** komponen yang ada.
3. **Bikin baru** hanya kalau memang tak ada — lalu update inventory.

Kalau ragu di tengah jalan, hentikan dan kerjakan ulang Langkah 1 — kecepatan tanpa cek ulang hampir selalu menghasilkan markup yang bentrok dengan komponen yang sudah ada.

---

## Protokol Wajib: Update CHANGELOG.md Sebelum Git Push

Sebelum melakukan git commit dan git push ke remote repository, **WAJIB** memperbarui berkas CHANGELOG.md di root proyek.

### Aturan Penulisan CHANGELOG:
1. **Berbasis Data Riil dan Menyeluruh (Bukan Parsial 1 Sesi):**
   - Jalankan git status dan telaah seluruh file yang bertambah/berubah di seluruh workspace (backend PHP/Laravel, frontend Vue/Inertia, CSS, database migration, routing, hingga file konfigurasi/testing).
   - Jangan hanya mencatat 1 perubahan kecil dari sesi prompt terakhir. Changelog harus mencerminkan **akumulasi seluruh perubahan riil** yang ada di changeset.
2. **Format Standar [Keep a Changelog](https://keepachangelog.com/id/1.0.0/):**
   - Gunakan kategori standar:
     - Added untuk fitur, komponen, endpoint, atau migrasi baru.
     - Changed untuk perubahan fungsionalitas, refactor komponen, atau perubahan token styling.
     - Fixed untuk perbaikan bug, error query/database, atau perbaikan tampilan.
     - Removed untuk file, komponen, atau kode yang dihapus.
   - Sebutkan konteks teknis yang jelas (misal: nama file komponen App*, service domain, nama tabel/constraint migrasi, breakpoint CSS).
3. **Alur Eksekusi:**
   1. git status / git diff --stat untuk inventarisasi semua berkas yang termodifikasi.
   2. Edit CHANGELOG.md pada bagian [Unreleased] atau header tanggal rilis terkait.
   3. Stage berkas CHANGELOG.md bersama perubahan lainnya (git add CHANGELOG.md), jalankan linter jika relevan, lalu commit dan push.

