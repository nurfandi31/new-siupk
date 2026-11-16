# Live Audit — CRUD Anggota, Pinjaman/Angsuran, Laporan PDF & Excel

- Tanggal: 2026-11-16T04:23:47.523Z
- Lokasi: http://asta.siupknext.test
- Font: http://siupknext.test
- Anggota uji: Anggota Angsuran 678842 (row_id 47)
- Pinjaman uji: row_id 49
- Screenshot: C:\laragon\www\siupknext\docs\audit\live-tenancy
- Berkas ekspor: C:\laragon\www\siupknext\docs\audit\live-tenancy\exports

## Ringkasan

| Status | Jumlah |
|---|---|
| PASS | 140 |
| FAIL | 0 |
| SKIP | 0 |

## Detail

| # | Area | Langkah | Status | Detail |
|---|---|---|---|---|
| 1 | Auth | Login manual admin tenant | PASS | http://asta.siupknext.test/dashboard |
| 2 | Anggota | Tambah & simpan anggota baru | PASS | Anggota Alur 624024 / 3204480262402483 (row_id 46) |
| 3 | Anggota | Buka detail & verifikasi NIK | PASS | http://asta.siupknext.test/master-data/members/46 |
| 4 | Anggota | Edit nomor HP & simpan | PASS | HP diperbarui menjadi 081298765432 |
| 5 | Anggota | Hapus anggota (konfirmasi dialog) | PASS | anggota hilang dari daftar |
| 6 | Pinjaman | Siapkan anggota peminjam | PASS | Anggota Angsuran 678842 (row_id 47) |
| 7 | Pinjaman | Buat proposal pinjaman individu | PASS | loan row_id 49 |
| 8 | Pinjaman | Verifikasi proposal | PASS | HTTP 303 |
| 9 | Pinjaman | Tetapkan alokasi | PASS | HTTP 303 |
| 10 | Pinjaman | Catat pencairan & bentuk jadwal angsuran | PASS | HTTP 303 |
| 11 | Pinjaman | Verifikasi status aktif & jadwal angsuran | PASS | 4 baris jadwal; angsuran pertama pokok 48.000, jasa 72.000, jatuh tempo 2026-11-16 |
| 12 | Angsuran | Posting pembayaran angsuran pertama | PASS | HTTP 302 |
| 13 | Angsuran | Verifikasi angsuran tercatat di detail pinjaman | PASS | 1/4  ANGSURAN |
| 14 | Laporan | Portfolio (/lending/reports/portfolio) | PASS | HTTP 200, 2518 char |
| 15 | Laporan | Jatuh Tempo (/lending/reports/due-today) | PASS | HTTP 200, 2561 char |
| 16 | Laporan | Tunggakan (/lending/reports/overdue) | PASS | HTTP 200, 2297 char |
| 17 | Laporan | Pinjaman Lunas (/lending/reports/paid) | PASS | HTTP 200, 2028 char |
| 18 | Laporan | Proposal (/lending/reports/proposals) | PASS | HTTP 200, 2156 char |
| 19 | Laporan | Verifikasi (/lending/reports/verifications) | PASS | HTTP 200, 1887 char |
| 20 | Laporan | Waiting List (/lending/reports/waiting-list) | PASS | HTTP 200, 1900 char |
| 21 | Laporan | Penghapusan (/lending/reports/write-offs) | PASS | HTTP 200, 2176 char |
| 22 | Laporan | Penghapusan Individu (/lending/reports/write-offs-individu) | PASS | HTTP 200, 2187 char |
| 23 | Laporan | Near Settlement (/lending/reports/near-settlement) | PASS | HTTP 200, 2739 char |
| 24 | Laporan | Jadwal vs Aktual (/lending/reports/schedule-vs-actual) | PASS | HTTP 200, 1881 char |
| 25 | Laporan | Jadwal vs Aktual Individu (/lending/reports/schedule-vs-actual-individu) | PASS | HTTP 200, 4342 char |
| 26 | Laporan | Kolek Desa (/lending/reports/kolek-desa) | PASS | HTTP 200, 2041 char |
| 27 | Laporan | Kolek Individu (/lending/reports/kolek-individu) | PASS | HTTP 200, 2654 char |
| 28 | Laporan | Kolek Kelompok (/lending/reports/kolek-kelompok) | PASS | HTTP 200, 1837 char |
| 29 | Laporan | Kolek Desa Individu (/lending/reports/kolek-desa-individu) | PASS | HTTP 200, 2034 char |
| 30 | Laporan | LPP Desa (/lending/reports/lpp-desa) | PASS | HTTP 200, 2034 char |
| 31 | Laporan | LPP Individu (/lending/reports/lpp-individu) | PASS | HTTP 200, 2717 char |
| 32 | Laporan | LPP Kelompok (/lending/reports/lpp-kelompok) | PASS | HTTP 200, 2564 char |
| 33 | Laporan | LPP Desa Individu (/lending/reports/lpp-desa-individu) | PASS | HTTP 200, 2032 char |
| 34 | Laporan | Cadangan Penghapusan (/lending/reports/cadangan-penghapusan) | PASS | HTTP 200, 1876 char |
| 35 | Laporan | Cadangan Penghapusan Individu (/lending/reports/cadangan-penghapusan-individu) | PASS | HTTP 200, 1863 char |
| 36 | Laporan | Anggota Aktif (/lending/reports/members/active) | PASS | HTTP 200, 2537 char |
| 37 | Laporan | Kelompok Aktif (/lending/reports/groups/active) | PASS | HTTP 200, 1911 char |
| 38 | Laporan | Pemanfaat Aktif (/lending/reports/beneficiaries/active) | PASS | HTTP 200, 1766 char |
| 39 | Laporan | Neraca (/accounting/reports/balance-sheet) | PASS | HTTP 200, 1865 char |
| 40 | Laporan | Laba Rugi (/accounting/reports/income-statement) | PASS | HTTP 200, 6413 char |
| 41 | Laporan | Arus Kas (/accounting/reports/cash-flow) | PASS | HTTP 200, 2349 char |
| 42 | Laporan | Buku Besar (/accounting/reports/general-ledger) | PASS | HTTP 200, 1588 char |
| 43 | Laporan | Neraca Saldo (/accounting/reports/trial-balance) | PASS | HTTP 200, 2008 char |
| 44 | Laporan | Perubahan Ekuitas (/accounting/reports/equity-change) | PASS | HTTP 200, 1977 char |
| 45 | Laporan | Jurnal (/accounting/reports/journals) | PASS | HTTP 200, 3676 char |
| 46 | Laporan | Anggaran (/accounting/reports/budgeting) | PASS | HTTP 200, 7869 char |
| 47 | Laporan | Kesehatan Keuangan (/accounting/reports/financial-health) | PASS | HTTP 200, 2205 char |
| 48 | Laporan | CALK (/accounting/reports/calk) | PASS | HTTP 200, 2166 char |
| 49 | Laporan | Simpanan (/accounting/reports/simpanan) | PASS | HTTP 200, 1823 char |
| 50 | Laporan | OJK Kesehatan (/regulatory/ojk/health) | PASS | HTTP 200, 2606 char |
| 51 | Laporan | OJK Kolektibilitas (/regulatory/ojk/collectibility) | PASS | HTTP 200, 2785 char |
| 52 | Laporan | OJK Kolektibilitas v2 (/regulatory/ojk/collectibility-v2) | PASS | HTTP 200, 2443 char |
| 53 | Laporan | OJK Profil (/regulatory/ojk/profile) | PASS | HTTP 200, 3255 char |
| 54 | Laporan | OJK Simpanan (/regulatory/ojk/savings) | PASS | HTTP 200, 2498 char |
| 55 | Laporan | OJK Pinjaman Aktif (/regulatory/ojk/active-loans) | PASS | HTTP 200, 2748 char |
| 56 | Laporan | OJK Penyisihan (/regulatory/ojk/allowance) | PASS | HTTP 200, 3080 char |
| 57 | Laporan | OJK Neraca (/regulatory/ojk/balance-sheet) | PASS | HTTP 200, 2525 char |
| 58 | Laporan | OJK Cover (/regulatory/ojk/cover) | PASS | HTTP 200, 2225 char |
| 59 | Laporan | OJK Laba Rugi (/regulatory/ojk/income-statement) | PASS | HTTP 200, 6891 char |
| 60 | Laporan | OJK Jasa Simpanan (/regulatory/ojk/interest) | PASS | HTTP 200, 2502 char |
| 61 | Laporan | OJK Pinjaman Diterima (/regulatory/ojk/loans-received) | PASS | HTTP 200, 2163 char |
| 62 | Laporan | OJK Pinjaman Lunas Kelompok (/regulatory/ojk/paid-group) | PASS | HTTP 200, 2221 char |
| 63 | Laporan | OJK Pinjaman Lunas Individu (/regulatory/ojk/paid-individu) | PASS | HTTP 200, 2221 char |
| 64 | Laporan | OJK Piutang Simpanan (/regulatory/ojk/savings-receivables) | PASS | HTTP 200, 2448 char |
| 65 | Laporan | Aset Tetap & Aset Tidak Berwujud | INFO | Tidak ada halaman UI; hanya tersedia ekspor PDF/Excel (divalidasi di matriks ekspor) |
| 66 | Ekspor UI | Tombol PDF & Excel — Jatuh Tempo | PASS | PDF 3271 byte (/lending/reports/due-today/pdf?as_of=2026-11-16); Excel 3122 byte (/lending/reports/due-today/excel?as_of=2026-11-16) |
| 67 | Ekspor UI | Tombol PDF & Excel — Tunggakan | PASS | PDF 2709 byte (/lending/reports/overdue/pdf?as_of=2026-11-16); Excel 2858 byte (/lending/reports/overdue/excel?as_of=2026-11-16) |
| 68 | Ekspor UI | Tombol PDF & Excel — Neraca | PASS | PDF 2214 byte (/accounting/reports/balance-sheet/pdf?year=2026&month=11); Excel 2610 byte (/accounting/reports/balance-sheet/excel?year=2026&month=11) |
| 69 | Ekspor UI | Tombol PDF & Excel — Laba Rugi | PASS | PDF 7717 byte (/accounting/reports/income-statement/pdf?year=2026&month=11); Excel 2639 byte (/accounting/reports/income-statement/excel?year=2026&month=11) |
| 70 | Matriks Ekspor | lending-portfolio .pdf | PASS | HTTP 200, 3659 byte, application/pdf |
| 71 | Matriks Ekspor | lending-due-today .pdf | PASS | HTTP 200, 3271 byte, application/pdf |
| 72 | Matriks Ekspor | lending-due-today .excel | PASS | HTTP 200, 3122 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 73 | Matriks Ekspor | lending-overdue .pdf | PASS | HTTP 200, 2709 byte, application/pdf |
| 74 | Matriks Ekspor | lending-overdue .excel | PASS | HTTP 200, 2858 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 75 | Matriks Ekspor | lending-paid .pdf | PASS | HTTP 200, 2767 byte, application/pdf |
| 76 | Matriks Ekspor | lending-proposals .pdf | PASS | HTTP 200, 3124 byte, application/pdf |
| 77 | Matriks Ekspor | lending-proposals .excel | PASS | HTTP 200, 3082 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 78 | Matriks Ekspor | lending-verifications .pdf | PASS | HTTP 200, 2632 byte, application/pdf |
| 79 | Matriks Ekspor | lending-verifications .excel | PASS | HTTP 200, 2870 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 80 | Matriks Ekspor | lending-waiting-list .pdf | PASS | HTTP 200, 2742 byte, application/pdf |
| 81 | Matriks Ekspor | lending-waiting-list .excel | PASS | HTTP 200, 2874 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 82 | Matriks Ekspor | lending-write-offs .pdf | PASS | HTTP 200, 2840 byte, application/pdf |
| 83 | Matriks Ekspor | lending-write-offs .excel | PASS | HTTP 200, 2840 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 84 | Matriks Ekspor | lending-write-offs-individu .pdf | PASS | HTTP 200, 2907 byte, application/pdf |
| 85 | Matriks Ekspor | lending-write-offs-individu .excel | PASS | HTTP 200, 2837 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 86 | Matriks Ekspor | lending-near-settlement .pdf | PASS | HTTP 200, 3940 byte, application/pdf |
| 87 | Matriks Ekspor | lending-schedule-vs-actual .pdf | PASS | HTTP 200, 2458 byte, application/pdf |
| 88 | Matriks Ekspor | lending-schedule-vs-actual-individu .pdf | PASS | HTTP 200, 10082 byte, application/pdf |
| 89 | Matriks Ekspor | lending-schedule-vs-actual-individu .excel | PASS | HTTP 200, 3738 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 90 | Matriks Ekspor | lending-kolek-desa .pdf | PASS | HTTP 200, 2926 byte, application/pdf |
| 91 | Matriks Ekspor | lending-kolek-individu .pdf | PASS | HTTP 200, 4941 byte, application/pdf |
| 92 | Matriks Ekspor | lending-kolek-kelompok .pdf | PASS | HTTP 200, 1450 byte, application/pdf |
| 93 | Matriks Ekspor | lending-kolek-desa-individu .pdf | PASS | HTTP 200, 2925 byte, application/pdf |
| 94 | Matriks Ekspor | lending-lpp-desa .pdf | PASS | HTTP 200, 3674 byte, application/pdf |
| 95 | Matriks Ekspor | lending-lpp-individu .pdf | PASS | HTTP 200, 6274 byte, application/pdf |
| 96 | Matriks Ekspor | lending-lpp-kelompok .pdf | PASS | HTTP 200, 6202 byte, application/pdf |
| 97 | Matriks Ekspor | lending-lpp-desa-individu .pdf | PASS | HTTP 200, 3457 byte, application/pdf |
| 98 | Matriks Ekspor | lending-cadangan .pdf | PASS | HTTP 200, 2950 byte, application/pdf |
| 99 | Matriks Ekspor | lending-cadangan-individu .pdf | PASS | HTTP 200, 2954 byte, application/pdf |
| 100 | Matriks Ekspor | lending-members-active .pdf | PASS | HTTP 200, 4659 byte, application/pdf |
| 101 | Matriks Ekspor | lending-groups-active .pdf | PASS | HTTP 200, 2656 byte, application/pdf |
| 102 | Matriks Ekspor | lending-beneficiaries-active .pdf | PASS | HTTP 200, 1892 byte, application/pdf |
| 103 | Matriks Ekspor | accounting-balance-sheet .pdf | PASS | HTTP 200, 2214 byte, application/pdf |
| 104 | Matriks Ekspor | accounting-balance-sheet .excel | PASS | HTTP 200, 2610 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 105 | Matriks Ekspor | accounting-income-statement .pdf | PASS | HTTP 200, 7717 byte, application/pdf |
| 106 | Matriks Ekspor | accounting-income-statement .excel | PASS | HTTP 200, 2639 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 107 | Matriks Ekspor | accounting-cash-flow .pdf | PASS | HTTP 200, 2424 byte, application/pdf |
| 108 | Matriks Ekspor | accounting-cash-flow .excel | PASS | HTTP 200, 2662 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 109 | Matriks Ekspor | accounting-general-ledger .pdf | PASS | HTTP 200, 3236 byte, application/pdf |
| 110 | Matriks Ekspor | accounting-general-ledger .excel | PASS | HTTP 200, 2930 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 111 | Matriks Ekspor | accounting-trial-balance .pdf | PASS | HTTP 200, 3183 byte, application/pdf |
| 112 | Matriks Ekspor | accounting-trial-balance .excel | PASS | HTTP 200, 2870 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 113 | Matriks Ekspor | accounting-equity-change .pdf | PASS | HTTP 200, 2097 byte, application/pdf |
| 114 | Matriks Ekspor | accounting-equity-change .excel | PASS | HTTP 200, 2547 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 115 | Matriks Ekspor | accounting-journals .pdf | PASS | HTTP 200, 4567 byte, application/pdf |
| 116 | Matriks Ekspor | accounting-journals .excel | PASS | HTTP 200, 2618 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 117 | Matriks Ekspor | accounting-budgeting .pdf | PASS | HTTP 200, 13727 byte, application/pdf |
| 118 | Matriks Ekspor | accounting-budgeting .excel | PASS | HTTP 200, 6822 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 119 | Matriks Ekspor | accounting-financial-health .pdf | PASS | HTTP 200, 4449 byte, application/pdf |
| 120 | Matriks Ekspor | accounting-calk .pdf | PASS | HTTP 200, 3076 byte, application/pdf |
| 121 | Matriks Ekspor | accounting-simpanan .pdf | PASS | HTTP 200, 1755 byte, application/pdf |
| 122 | Matriks Ekspor | accounting-simpanan .excel | PASS | HTTP 200, 2801 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 123 | Matriks Ekspor | accounting-assets-fixed .pdf | PASS | HTTP 200, 1449 byte, application/pdf |
| 124 | Matriks Ekspor | accounting-assets-fixed .excel | PASS | HTTP 200, 2824 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 125 | Matriks Ekspor | accounting-assets-intangible .pdf | PASS | HTTP 200, 1449 byte, application/pdf |
| 126 | Matriks Ekspor | accounting-assets-intangible .excel | PASS | HTTP 200, 2836 byte, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet |
| 127 | Matriks Ekspor | ojk-health .pdf | PASS | HTTP 200, 6170 byte, application/pdf |
| 128 | Matriks Ekspor | ojk-collectibility .pdf | PASS | HTTP 200, 7275 byte, application/pdf |
| 129 | Matriks Ekspor | ojk-collectibility-v2 .pdf | PASS | HTTP 200, 7579 byte, application/pdf |
| 130 | Matriks Ekspor | ojk-profile .pdf | PASS | HTTP 200, 5288 byte, application/pdf |
| 131 | Matriks Ekspor | ojk-savings .pdf | PASS | HTTP 200, 1978 byte, application/pdf |
| 132 | Matriks Ekspor | ojk-active-loans .pdf | PASS | HTTP 200, 4473 byte, application/pdf |
| 133 | Matriks Ekspor | ojk-allowance .pdf | PASS | HTTP 200, 5953 byte, application/pdf |
| 134 | Matriks Ekspor | ojk-balance-sheet .pdf | PASS | HTTP 200, 3196 byte, application/pdf |
| 135 | Matriks Ekspor | ojk-cover .pdf | PASS | HTTP 200, 2013 byte, application/pdf |
| 136 | Matriks Ekspor | ojk-income-statement .pdf | PASS | HTTP 200, 8226 byte, application/pdf |
| 137 | Matriks Ekspor | ojk-interest .pdf | PASS | HTTP 200, 2844 byte, application/pdf |
| 138 | Matriks Ekspor | ojk-loans-received .pdf | PASS | HTTP 200, 3305 byte, application/pdf |
| 139 | Matriks Ekspor | ojk-paid-group .pdf | PASS | HTTP 200, 3099 byte, application/pdf |
| 140 | Matriks Ekspor | ojk-paid-individu .pdf | PASS | HTTP 200, 3128 byte, application/pdf |
| 141 | Matriks Ekspor | ojk-savings-receivables .pdf | PASS | HTTP 200, 3024 byte, application/pdf |

## Console / Page Errors

- Tidak ada error console yang tercatat.
