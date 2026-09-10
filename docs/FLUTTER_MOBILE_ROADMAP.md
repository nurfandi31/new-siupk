# Roadmap & Arsitektur SIUPK Mobile App (Flutter)

Dokumen ini memetakan arsitektur, pembagian tanggung jawab fitur, strategi integrasi API multi-tenant, dan tahapan implementasi aplikasi mobile **SIUPK Mobile** berbasis **Flutter**.

---

## 1. Ringkasan Eksekutif & Tujuan

Aplikasi mobile **SIUPK Mobile** dirancang sebagai pendamping operasional (*companion app*) untuk melengkapi sistem **SIUPK Next Web & Desktop**. 

### Tujuan Utama:
1. **Kecepatan Operasional Lapangan**: Memfasilitasi petugas penagihan (kolektor/mantri) dan surveyor lapangan dengan aplikasi ringan, responsif, dan siap digunakan langsung di depan nasabah.
2. **Pemanfaatan Sensor & Hardware Perangkat**: Integrasi langsung dengan printer thermal Bluetooth (58mm/80mm), kamera ponsel dengan geo-tagging GPS, dan kanvas tanda tangan digital.
3. **Ketahanan di Lapangan (*Offline-First*)**: Mampu mencatat setoran dan data survei di wilayah desa tanpa sinyal internet, lalu melakukan sinkronisasi otomatis ke cloud server begitu terhubung internet.
4. **Pengambilan Keputusan Cepat (*On-the-Go Approval*)**: Memungkinkan manajer dan pengurus untuk memantau performa harian dan menyetujui (*approve*) proposal pinjaman di mana saja melalui notifikasi push seketika.

---

## 2. Arsitektur Sistem: *Multi-Tenant Backend + Flutter Clean Architecture*

```
   +-------------------------------------------------------------+
   |            BACKEND CLOUD SERVER (LARAVEL 13)                |
   |  - Database Sharding Platform & Tenant Shards (MySQL)       |
   |  - Authentication: Laravel Sanctum (Bearer Token)           |
   |  - Context: Multi-Tenant Resolver (X-Tenant-Code / Token)   |
   |  - Core Accounting, Loan Engine, & WhatsApp Gateway API     |
   +------------------------------+------------------------------+
                                  |
                                  | HTTPS RESTful JSON API
                                  | (Sanctum Auth + Push FCM)
                                  v
   +-------------------------------------------------------------+
   |              SIUPK MOBILE APP (FLUTTER 3.x)                 |
   |                                                             |
   |  +-------------------------------------------------------+  |
   |  | Presentation Layer: BLoC / Riverpod + Material 3 UI   |  |
   |  +-------------------------------------------------------+  |
   |  | Domain Layer: Use Cases, Repositories, Entities       |  |
   |  +-------------------------------------------------------+  |
   |  | Data Layer: Dio Client + Interceptors + Outbox Queue  |  |
   |  +-------------------------------------------------------+  |
   |  | Local Storage: Isar / Drift (SQLite) + SecureStorage  |  |
   |  +-------------------------------------------------------+  |
   |  | Hardware: Bluetooth Thermal ESC/POS, GPS, Camera, Sign|  |
   |  +-------------------------------------------------------+  |
   +-------------------------------------------------------------+
```

---

## 3. Matriks Pemisahan Fitur: Mobile vs Web / Desktop

Pemisahan fitur ditentukan secara ketat berdasarkan kenyamanan antarmuka (UX), efisiensi hardware, keamanan, dan beban kognitif kerja.

| Modul & Fitur | Mobile App (Flutter) | Web / Desktop | Alasan Pemisahan |
|---|:---:|:---:|---|
| **Otentikasi & Profil Pengguna** | âœ… Token Mobile | âœ… Sesi Web / Token Desktop | Pengguna login sesuai perangkat masing-masing. |
| **Koleksi & Setoran Angsuran Lapangan** | âœ… **Prioritas Utama** | âœ… Di Kantor | Mobile mendukung cetak struk printer thermal Bluetooth & kirim WhatsApp instan. |
| **Cetak Struk Thermal (58/80mm)** | âœ… Native Bluetooth | âŒ Terbatas (RawBT) | Cetak struk portabel langsung di depan nasabah saat transaksi lapangan. |
| **Survei & Verifikasi Proposal** | âœ… **Prioritas Utama** | âœ… Review Dokumen | Pemanfaatan kamera foto jaminan, watermark koordinat GPS, & tanda tangan layar sentuh. |
| **Persetujuan Pinjaman (Quick Approval)** | âœ… 1-Tap Action | âœ… Detail Komite | Pengurus/Manajer dapat menyetujui atau menolak proposal secara cepat saat mobile. |
| **Executive Dashboard Ringkas** | âœ… KPI & Ringkasan | âœ… Analisis Penuh | Menampilkan kas hari ini, realisasi bulanan, rasio NPL/PAR, dan jatuh tempo. |
| **Asisten AI (Ariel Voice/Chat)** | âœ… Chat Ringkas | âœ… Web Widget | Konsultasi kilat kondisi keuangan tenant melalui smartphone. |
| **Tutup Buku Bulanan & Tahunan** | âŒ **Dilarang** | âœ… **Wajib Desktop/Web** | Proses kritis akuntansi: rekonsiliasi, pembagian surplus PADes, penguncian fiskal. |
| **Bagan Akun (Chart of Accounts)** | âŒ Hanya Lihat | âœ… **Wajib Desktop/Web** | Pengelolaan pohon hierarki akun 4-5 digit membutuhkan ruang visual lebar. |
| **Jurnal Umum Memorial Multi-Baris** | âŒ Tidak Efisien | âœ… **Wajib Desktop/Web** | Input jurnal penyesuaian 5-10 baris debit/kredit lebih akurat di keyboard fisik. |
| **Laporan Keuangan Standar SAK (Neraca/LR)** | âš ï¸ Ringkasan Saja | âœ… **Laporan Lengkap & PDF** | Dokumen multi-halaman dan spreadsheet analisis komparatif tahunan. |
| **Konsolidasi Holding (Kabupaten/Provinsi)** | âš ï¸ Dashboard Global | âœ… **Matriks Ratusan Kolom** | Tabel konsolidasi multi-kecamatan membutuhkan resolusi layar monitor besar. |
| **E-Budgeting & Rencana Anggaran (RAB)** | âŒ Terlalu Kompleks | âœ… **Wajib Desktop/Web** | Input ratusan komponen akun biaya dan target pendapatan per bulan. |
| **Import / Export Massal (CSV & Excel)** | âŒ Tidak Praktis | âœ… **Wajib Desktop/Web** | Pemrosesan berkas migrasi ribuan data nasabah dan saldo awal. |
| **Konfigurasi Sistem & Superadmin Platform** | âŒ **Dilarang** | âœ… **Wajib Desktop/Web** | Manajemen database sharding, billing gateway, data purifier, dan matriks hak akses. |

---

## 4. Rencana Kerja & Tahapan Implementasi (Implementation Roadmap)

---

### **FASE 0: Fondasi Backend API & Keamanan (Laravel 13)**
- [x] **0.1. Instalasi & Konfigurasi Laravel Sanctum**
- [x] **0.2. Middleware Multi-Tenant API (`tenant.api`)**
- [x] **0.3. Standard API Response & Exception Handler**
- [x] **0.4. Auth Endpoints API**
- [x] **0.5. Automated Test Backend API (7 passed)**

---

### **FASE 1: Fondasi Proyek Flutter & Clean Architecture**
- [x] **1.1. Inisialisasi Proyek & Clean Architecture (`core/`, `features/`)**
- [x] **1.2. State Management (BLoC) & Service Locator (GetIt)**
- [x] **1.3. Networking Client (Dio + Interceptors)**
- [x] **1.4. Secure Storage Token & Session Cache**
- [x] **1.5. Design System Material 3 Minimalis & Ringkas**

---

### **FASE 2: Modul Kolektor & Kasir Lapangan (P0 - Critical)**
- [x] **2.1. Backend API Koleksi & Angsuran (`MobileCollectionController`)**
- [x] **2.2. UI Mobile Alur Penagihan (`CollectionListPage` & `PaymentPage`)**
- [x] **2.3. Integrasi Cetak Struk Printer Thermal Bluetooth (`ThermalPrinterService`)**
- [x] **2.4. Integrasi WhatsApp Receipt Trigger (`WhatsAppHelper`)**
- [x] **2.5. Feature Test Otomatis (3 passed, 21 assertions)**

---

### **FASE 3: Modul Verifikasi & Survei Lapangan (P1 - High)**
- [x] **3.1. Backend API Verifikasi Proposal (`MobileVerificationController`)**
- [x] **3.2. Form Checksheet Verifikasi 5C (`SurveyFormPage`)**
- [x] **3.3. Integrasi Kamera & Geo-Tagging GPS (`CameraGpsWidget`)**
- [x] **3.4. Kanvas Tanda Tangan Digital (`SignatureCanvasWidget`)**
- [x] **3.5. Feature Test Otomatis (`MobileVerificationApiTest` - 4 passed, 32 assertions)**

---

### **FASE 4: Modul Eksekutif & Persetujuan (P1 - High)**
- [x] **4.1. Backend API Executive Dashboard & Approval (`MobileExecutiveController`)**
- [x] **4.2. UI Executive Dashboard Ringkas (`ExecutiveDashboardPage`)**
- [x] **4.3. Quick Action Persetujuan Pinjaman (`ApprovalListPage` & `ApprovalDetailPage`)**
- [x] **4.4. Feature Test Otomatis (`MobileExecutiveApiTest` - 5 passed, 33 assertions)**

---

### **FASE 5: Modul Asisten AI Ariel On-The-Go**
- [x] **5.1. UI Chat Interaktif Asisten AI Ariel (`AssistantChatPage`)**
- [x] **5.2. Preset Saran Pertanyaan Cepat Operasional**
