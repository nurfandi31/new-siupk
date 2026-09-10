# AI In-App Assistant — Project Guide

> Dokumen ini adalah panduan pengembangan untuk project AI in-app chatbot
> (customer service + task assistant) yang berdiri sendiri di luar scope
> Enpii Studio. Dokumen mencakup overview, struktur database, dan roadmap
> pengerjaan.

---

## 1. Project Overview

### 1.1 Latar Belakang

Aplikasi SaaS klien sering menerima pertanyaan berulang (FAQ, cara pakai
fitur) dan membutuhkan bantuan eksekusi tugas rutin (input transaksi,
download laporan). Menjawab manual berulang kali tidak scalable. Project
ini membangun sebuah **in-app AI assistant** yang bisa dipasang di
aplikasi mana pun (multi-tenant, multi-klien) untuk menjawab pertanyaan
dan mengeksekusi tugas atas nama pengguna.

### 1.2 Dua Peran Utama

1. **Customer Service** — menjawab pertanyaan berdasarkan basis
   pengetahuan (dokumentasi, FAQ, kebijakan) tiap aplikasi/klien.
2. **Task Assistant** — mengeksekusi aksi nyata (input transaksi, generate
   & download laporan, dll) lewat pemanggilan API aplikasi klien.

### 1.3 Prinsip Desain

- **Self-hosted LLM**, bukan langganan API per-pemakaian (mis. Qwen3.5-9B
  atau varian lain sesuai kebutuhan beban).
- **RAG untuk pengetahuan**, **tool-calling (function calling) untuk
  aksi** — dua mekanisme berbeda, jangan dicampur jadi satu solusi.
- **Multi-tenant dari awal** — satu instance assistant melayani banyak
  aplikasi/klien, dengan isolasi data ketat antar tenant.
- **Orchestrator terpusat + adapter tipis per aplikasi klien** — logic
  inti (RAG, tool-calling loop, model routing) satu tempat; tiap aplikasi
  klien hanya mengekspos endpoint aksi miliknya sendiri lewat kontrak
  yang seragam.
- **Aksi sensitif wajib konfirmasi** sebelum eksekusi final, dan semua
  eksekusi tercatat di audit log.
- **Karena berdiri sendiri di luar Enpii Studio**, project ini tidak
  bergantung pada infrastruktur internal Enpii Studio (cross-app-bus,
  SSO internal, dsb.) — harus punya mekanisme otentikasi & manajemen
  tenant sendiri.

### 1.4 Komponen Utama

| Komponen | Fungsi |
|---|---|
| Orchestrator Service | Menerima pesan, klasifikasi intent, jalankan RAG atau tool-calling loop |
| Model Gateway | Endpoint terpusat ke LLM self-hosted (dan fallback model lain jika perlu) |
| Knowledge Store | Dokumen + embedding vector per tenant untuk RAG |
| Tool Registry | Daftar aksi yang tersedia per tenant, dengan skema parameter |
| Tenant Adapter (di aplikasi klien) | Endpoint API yang mengeksekusi aksi nyata di aplikasi klien |
| Admin Dashboard | Kelola tenant, tools, knowledge base, lihat audit log & analytics |

### 1.5 Alur Singkat

```
Pesan masuk (tenant X)
   -> Autentikasi tenant + resolve context
   -> Klasifikasi intent: pertanyaan vs aksi
        -> Pertanyaan: retrieval dari Knowledge Store tenant X -> jawab
        -> Aksi: pilih tool dari Tool Registry tenant X
             -> jika requires_confirmation: minta konfirmasi user dulu
             -> panggil endpoint Tenant Adapter aplikasi klien
             -> catat hasil ke audit log
   -> Kirim balasan ke user
```

---

## 2. Struktur Database

Skema di bawah adalah skema untuk **Orchestrator Service** (bukan skema
aplikasi klien). Vector embedding disarankan disimpan di vector store
khusus (mis. pgvector/Qdrant), bukan tabel relasional biasa.

### 2.1 `tenants`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| name | string | Nama aplikasi/klien |
| slug | string, unique | |
| status | enum(active, suspended, trial) | |
| plan | string | Untuk keperluan billing/limit ke depan |
| created_at, updated_at | timestamp | |

### 2.2 `api_keys`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| tenant_id | uuid, FK -> tenants | |
| key_hash | string | Hash dari API key, bukan plaintext |
| scopes | json | Mis. `["chat:read","chat:write","tools:manage"]` |
| revoked_at | timestamp, nullable | |
| created_at | timestamp | |

### 2.3 `conversations`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| tenant_id | uuid, FK | |
| external_user_id | string | ID user di aplikasi klien (bukan user internal) |
| channel | string | web, mobile, dsb. |
| status | enum(open, closed) | |
| summary | text, nullable | Ringkasan riwayat lama hasil compaction (lihat bagian 4) |
| last_activity_at | timestamp, nullable | Dipakai untuk deteksi idle timeout & pemicu compaction |
| started_at, ended_at | timestamp | |

### 2.4 `messages`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| conversation_id | uuid, FK | |
| role | enum(user, assistant, tool) | |
| content | text | |
| tool_call_json | json, nullable | Tool + parameter yang dipilih model |
| tool_result_json | json, nullable | Hasil eksekusi tool |
| created_at | timestamp | |

### 2.5 `tools`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| tenant_id | uuid, FK | |
| name | string | Mis. `input_transaksi`, `download_laporan` |
| description | text | Deskripsi untuk model |
| json_schema | json | Skema parameter (nama, tipe, wajib/opsional) |
| endpoint_url | string | Endpoint di Tenant Adapter yang dipanggil |
| requires_confirmation | boolean | |
| is_active | boolean | |
| created_at, updated_at | timestamp | |

### 2.6 `tool_executions`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| message_id | uuid, FK -> messages | |
| tool_id | uuid, FK -> tools | |
| tenant_id | uuid, FK | |
| input_params | json | |
| output | json, nullable | |
| status | enum(pending_confirmation, confirmed, executed, failed, rejected) | |
| requested_at | timestamp | |
| executed_at | timestamp, nullable | |

### 2.7 `confirmations`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| tool_execution_id | uuid, FK | |
| confirmed_by | string | external_user_id yang konfirmasi |
| confirmed_at | timestamp, nullable | |
| status | enum(pending, approved, rejected, expired) | |

### 2.8 `knowledge_sources`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| tenant_id | uuid, FK | |
| name | string | |
| source_type | enum(document, url, faq_manual) | |
| source_url | string, nullable | Diisi kalau `source_type = url`, target untuk scraping |
| sync_frequency | enum(manual, daily, weekly), nullable | Frekuensi re-scrape kalau `source_type = url` |
| status | enum(active, syncing, error) | |
| last_synced_at | timestamp, nullable | |

### 2.9 `documents`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| knowledge_source_id | uuid, FK | |
| title | string | |
| content_raw | text | Untuk `document`/`url`: teks hasil ekstraksi/scraping. Untuk `faq_manual`: bisa dikosongkan, pakai `question`/`answer` |
| question | text, nullable | Diisi kalau berasal dari `faq_manual` |
| answer | text, nullable | Diisi kalau berasal dari `faq_manual` |
| source_format | enum(pdf, docx, txt, md, manual, url), nullable | Asal konten, untuk keperluan tracking/debug |
| original_file_path | string, nullable | Path file asli kalau diupload (source_format pdf/docx/txt/md) |
| metadata | json, nullable | |
| created_at, updated_at | timestamp | |

#### Catatan Teknis: Parsing per `source_format`

Laravel (core framework) tidak punya kemampuan native parsing PDF/DOCX —
perlu package PHP tambahan:

| Format | Cara baca | Package/Tool |
|---|---|---|
| `txt` | Baca langsung, tanpa parsing | `Storage::get()` / `file_get_contents()` |
| `md` | Baca langsung, opsional convert ke plain text | `Storage::get()`, opsional `league/commonmark` |
| `pdf` | Ekstrak teks dari PDF berbasis teks | `smalot/pdfparser` (murni PHP), atau `spatie/pdf-to-text` (wrapper `pdftotext`, lebih akurat untuk layout kompleks tapi butuh binary poppler-utils di server) |
| `docx` | Ekstrak teks, heading, tabel | `phpoffice/phpword` |

**Catatan penting — PDF hasil scan:** `smalot/pdfparser` dan
`spatie/pdf-to-text` hanya bisa membaca PDF yang teksnya asli (bukan
hasil scan/foto). Kalau klien upload PDF hasil scan, hasil ekstraksi
akan kosong/rusak tanpa error yang jelas. Perlu OCR sebagai fallback
(mis. `thiagoalessio/tesseract_ocr` yang wrapping Tesseract OCR),
dipicu kalau hasil ekstraksi teks awal kosong atau di bawah ambang
batas panjang minimum.

**Pola eksekusi:** karena parsing PDF/DOCX (apalagi dengan fallback OCR)
bisa memakan waktu, proses ini dijalankan sebagai **job asinkron**
(queue), bukan sinkron saat upload:

```
User upload file
  -> knowledge_sources.status = 'syncing'
  -> Job queue: parsing sesuai source_format (+ OCR fallback jika perlu)
  -> content_raw terisi -> chunking -> embedding -> vector store
  -> knowledge_sources.status = 'active' (atau 'error' jika gagal)
```

### 2.10 `document_chunks` (referensi ke vector store)
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| document_id | uuid, FK | |
| chunk_index | integer | |
| chunk_text | text | |
| vector_ref_id | string | ID vector di vector store eksternal |

### 2.11 `audit_logs`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| tenant_id | uuid, FK | |
| actor | string | system / external_user_id |
| action | string | |
| entity_type, entity_id | string | |
| metadata | json, nullable | |
| created_at | timestamp | |

### 2.12 `usage_metrics`
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | uuid, PK | |
| tenant_id | uuid, FK | |
| date | date | |
| message_count | integer | |
| tool_call_count | integer | |
| token_usage | integer | |

---

## 3. Roadmap Pengerjaan

### Fase 0 — Riset & Fondasi
- [ ] Finalisasi model (Qwen3.5-9B atau varian lain) berdasarkan uji coba beban riil
- [ ] Setup infrastruktur inference (server, quantization, model gateway/API compatible endpoint)
- [ ] Tentukan vector store untuk RAG (pgvector / Qdrant / lainnya)
- [ ] Desain kontrak API antara Orchestrator dan Tenant Adapter (skema tool, auth, format response)

### Fase 1 — MVP: RAG-only Customer Service
- [ ] Skema database inti: tenants, api_keys, conversations, messages, knowledge_sources, documents
- [ ] Ingest `faq_manual`: form input pasangan pertanyaan-jawaban langsung dari Admin Dashboard (prioritas pertama, paling cepat diisi & akurat untuk retrieval)
- [ ] Ingest `document`: upload file (PDF/DOCX/TXT/MD) -> ekstraksi teks -> chunking -> embedding -> vector store
- [ ] Ingest `url`: scraping halaman -> ekstraksi teks -> chunking -> embedding, dengan re-sync berkala sesuai `sync_frequency`
- [ ] Endpoint chat dasar: terima pesan -> retrieval -> jawab dari LLM
- [ ] Session management dasar: idle timeout, sliding window pesan terakhir
- [ ] Uji coba dengan 1 tenant pilot, knowledge base terbatas

### Fase 2 — Tool-Calling Engine
- [ ] Tabel tools, tool_executions, confirmations
- [ ] Tool registry + validasi skema parameter
- [ ] Agent loop: klasifikasi intent -> pilih tool -> panggil Tenant Adapter -> tangani hasil
- [ ] Alur konfirmasi untuk aksi sensitif
- [ ] Audit logging untuk setiap eksekusi tool
- [ ] Job compaction otomatis (ringkasan riwayat lama ke `conversations.summary`)

### Fase 3 — Multi-Tenant Onboarding
- [ ] Dashboard admin: kelola tenant, api key, tools, knowledge source
- [ ] Self-service upload dokumen knowledge base per tenant
- [ ] Isolasi data antar tenant diuji secara eksplisit (tenant A tidak bisa akses data tenant B)

### Fase 4 — Observability & Analytics
- [ ] usage_metrics harian per tenant
- [ ] Dashboard analytics: pertanyaan terbanyak, tool paling sering dipakai, tingkat keberhasilan eksekusi
- [ ] Alerting untuk kegagalan tool berulang atau anomali penggunaan

### Fase 5 — Hardening & Skalabilitas
- [ ] Rate limiting per tenant
- [ ] Review keamanan (terutama alur konfirmasi aksi finansial)
- [ ] Uji beban untuk banyak tenant bersamaan
- [ ] Evaluasi upgrade model (mis. dari 9B ke ukuran lebih besar) berdasarkan data akurasi tool-selection riil

---

## 4. Session Management & Context Compaction

### 4.1 Session Management

- **Pembuatan sesi baru:** sesi (`conversations`) dianggap berakhir kalau
  tidak ada aktivitas selama periode idle tertentu (mis. 30 menit), diukur
  dari kolom `last_activity_at`. Pesan baru setelah idle timeout membuat
  `conversation` baru, bukan melanjutkan yang lama. User juga bisa
  eksplisit memulai sesi baru dari UI.
- **Apa yang dikirim ke LLM tiap request:** bukan seluruh isi tabel
  `messages` mentah, melainkan:
  1. `summary` dari `conversations` (ringkasan riwayat lama, kalau ada)
  2. N pesan terakhir mentah dari `messages` (jendela geser / sliding
     window)
  3. Instruksi persona/system prompt (lihat pembahasan sebelumnya)
- **Tool-calling yang masih menggantung:** kalau ada `tool_execution`
  berstatus `pending_confirmation` di sesi yang sama, itu wajib
  dimasukkan ke context sebelum memproses pesan baru user — supaya
  konfirmasi aksi tidak "hilang" di tengah percakapan atau tertimpa
  intent baru.

### 4.2 Context Compaction

Diperlukan karena riwayat chat bisa lebih panjang dari context window
model (apalagi model kelas 9B biasanya window-nya lebih kecil
dibanding model besar).

- **Strategi:** sliding window + ringkasan otomatis. Pesan yang keluar
  dari jendela N-terakhir diringkas oleh LLM secara periodik dan
  disimpan ke kolom `conversations.summary` (menggantikan/menggabungkan
  ringkasan sebelumnya).
- **Pemicu compaction:** dijalankan saat estimasi total token (summary +
  N pesan terakhir) mendekati ambang batas context window — bukan di
  setiap pesan, supaya tidak boros compute.
- **Yang wajib dipertahankan saat meringkas:** hasil `tool_executions`
  yang relevan untuk aksi susulan (mis. user bertanya ulang soal laporan
  yang tadi diminta) — ringkasan harus tetap menyebutkan aksi apa saja
  yang sudah dieksekusi dan hasil kuncinya, bukan cuma isi obrolan.
- **Implementasi:** proses ringkasan bisa jadi job asinkron terpisah
  (queue), dipanggil oleh Orchestrator, tidak menghalangi alur
  request-response chat yang sedang berjalan.

---

## 5. Komunikasi & Transport Layer

Ada tiga jalur komunikasi berbeda di arsitektur ini — jangan disamakan
protokolnya, karena karakteristik masing-masing berbeda.

### 4.1 User (chat UI) ↔ Orchestrator

- **Kirim pesan (user → orchestrator):** HTTP POST biasa. Sifatnya
  request-response sekali tembak.
- **Jawaban (orchestrator → user):** **SSE (Server-Sent Events)**, bukan
  WebSocket. Jawaban LLM di-stream token demi token untuk UX responsif,
  tapi ini komunikasi satu arah (server → client) sehingga tidak perlu
  koneksi bidirectional. Laravel bisa pakai `response()->stream()` tanpa
  dependency tambahan.
- **WebSocket (Laravel Reverb/Pusher) — opsional, belakangan:** hanya
  dibutuhkan kalau ada requirement nyata untuk push real-time dua arah,
  mis. typing indicator, sinkronisasi multi-device/multi-tab, atau
  notifikasi live saat user sedang idle. Bukan syarat MVP — tambahkan
  sebagai layer terpisah kalau memang dibutuhkan, supaya tidak menambah
  kompleksitas scaling (koneksi persisten) di awal.

### 4.2 Orchestrator ↔ Tenant Adapter (eksekusi tool)

- **Aksi cepat** (mis. `input_transaksi`): HTTP REST synchronous biasa —
  panggil endpoint, tunggu hasil, selesai.
- **Aksi lambat** (mis. `download_laporan` / generate laporan besar):
  hindari HTTP request yang menggantung lama (risiko timeout). Pola yang
  dipakai:
  1. Orchestrator panggil endpoint → Tenant Adapter langsung balas
     `job_id` + status `processing`.
  2. Tenant Adapter memproses di background (queue).
  3. Setelah selesai, Tenant Adapter memanggil **webhook** balik ke
     Orchestrator (`POST /tool-executions/{id}/complete`), atau
     Orchestrator melakukan polling status secara berkala.
  4. Orchestrator update `tool_executions.status` lalu kirim hasil ke
     user — lewat SSE kalau sesi chat masih berjalan, atau lewat
     notifikasi kalau sesi sudah berakhir.

### 4.3 Ringkasan

| Link | Pilihan | Kenapa |
|---|---|---|
| User → Orchestrator (kirim pesan) | HTTP POST | Request-response sederhana |
| Orchestrator → User (jawaban) | SSE (streaming) | Butuh stream teks, tidak butuh dua arah |
| Orchestrator ↔ Tenant Adapter (aksi cepat) | HTTP REST sync | Server-to-server, sederhana |
| Orchestrator ↔ Tenant Adapter (aksi lambat/laporan) | HTTP + webhook callback | Hindari long-hanging request |
| WebSocket (Reverb/Pusher) | Opsional, belakangan | Hanya kalau butuh push real-time dua arah (typing indicator, notifikasi live) |

**Implikasi ke roadmap:** implementasikan HTTP + SSE di Fase 1–2. Jangan
bangun WebSocket layer di awal — itu menambah kompleksitas infra
(koneksi persisten, scaling horizontal) yang belum tentu dibutuhkan di
MVP. Tambahkan hanya kalau muncul requirement nyata untuk push
real-time.

---

## 6. Catatan Tambahan

- Karena project ini di luar scope Enpii Studio, gunakan mekanisme
  otentikasi & manajemen tenant sendiri (API key per tenant), bukan
  bergantung pada SSO internal Enpii.
- Simpan repository ini terpisah dari repo-repo Enpii Studio yang sudah
  ada, dengan lisensi/kepemilikan yang jelas sejak awal.
