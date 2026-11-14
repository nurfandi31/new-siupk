<?php

declare(strict_types=1);

/**
 * Katalog menu aplikasi tenant.
 *
 * Dipakai oleh pusat tenant (/admin/tenants/{tenant}/menus) untuk membatasi
 * menu yang boleh dibuka tiap role. Path harus sama dengan href pada
 * resources/js/Layouts/AuthenticatedLayout.vue & TenantAdminLayout.vue.
 *
 * Catatan: menu hanya bisa MEMPERSEMPIT. Role tetap membutuhkan izin modul
 * (config/permissions.php) untuk membuka halaman terkait.
 */
return [
    'groups' => [
        [
            'key' => 'pengaturan',
            'label' => 'Pengaturan',
            'items' => [
                ['key' => 'settings.index', 'label' => 'Pengaturan', 'path' => '/settings'],
                ['key' => 'settings.whatsapp', 'label' => 'Gerbang WhatsApp', 'path' => '/settings/whatsapp'],
            ],
        ],
        [
            'key' => 'master-data',
            'label' => 'Master Data',
            'items' => [
                ['key' => 'villages.index', 'label' => 'Data Desa', 'path' => '/master-data/villages'],
                ['key' => 'members.create', 'label' => 'Tambah Anggota', 'path' => '/master-data/members/create'],
                ['key' => 'members.index', 'label' => 'Daftar Anggota', 'path' => '/master-data/members'],
                ['key' => 'groups.create', 'label' => 'Tambah Kelompok', 'path' => '/master-data/groups/create'],
                ['key' => 'groups.index', 'label' => 'Daftar Kelompok', 'path' => '/master-data/groups'],
                ['key' => 'institutions.create', 'label' => 'Tambah Lembaga', 'path' => '/master-data/institutions/create'],
                ['key' => 'institutions.index', 'label' => 'Daftar Lembaga', 'path' => '/master-data/institutions'],
            ],
        ],
        [
            'key' => 'pinjaman',
            'label' => 'Pinjaman',
            'items' => [
                ['key' => 'loans.create', 'label' => 'Proposal Kelompok', 'path' => '/lending/loans/create'],
                ['key' => 'member-loans.create', 'label' => 'Proposal Individu', 'path' => '/lending/member-loans/create'],
                ['key' => 'loans.index', 'label' => 'Tahapan Perguliran (Kelompok)', 'path' => '/lending/loans'],
                ['key' => 'member-loans.index', 'label' => 'Tahapan Perguliran (Individu)', 'path' => '/lending/member-loans'],
                ['key' => 'lending.simulation', 'label' => 'Simulasi Pinjaman', 'path' => '/lending/simulation'],
            ],
        ],
        [
            'key' => 'simpanan',
            'label' => 'Simpanan',
            'items' => [
                ['key' => 'simpanan.create', 'label' => 'Buka Rekening', 'path' => '/simpanan/create'],
                ['key' => 'simpanan.index', 'label' => 'Rekening Simpanan', 'path' => '/simpanan'],
                ['key' => 'simpanan.interest', 'label' => 'Bunga & Pajak', 'path' => '/simpanan/bunga'],
            ],
        ],
        [
            'key' => 'transaksi',
            'label' => 'Transaksi',
            'items' => [
                ['key' => 'journal-entries.create', 'label' => 'Jurnal Umum', 'path' => '/accounting/journal-entries/create'],
                ['key' => 'installments.group', 'label' => 'Jurnal Angsuran (Kelompok)', 'path' => '/accounting/journal-entries/installment'],
                ['key' => 'installments.individual', 'label' => 'Jurnal Angsuran (Individu)', 'path' => '/accounting/journal-entries/installment-individual'],
                ['key' => 'journals.index', 'label' => 'Daftar Jurnal', 'path' => '/accounting/journals'],
                ['key' => 'assets.index', 'label' => 'Daftar Inventaris', 'path' => '/accounting/assets'],
                ['key' => 'chart-of-accounts.index', 'label' => 'Bagan Akun', 'path' => '/accounting/chart-of-accounts'],
            ],
        ],
        [
            'key' => 'periodik',
            'label' => 'Periodik',
            'items' => [
                ['key' => 'budgeting.index', 'label' => 'E-Budgeting', 'path' => '/budgeting'],
                ['key' => 'period-close.index', 'label' => 'Tutup Buku', 'path' => '/accounting/period-close'],
                ['key' => 'tax-estimate.index', 'label' => 'Taksiran Pajak', 'path' => '/accounting/tax-estimate'],
            ],
        ],
        [
            'key' => 'pelaporan',
            'label' => 'Pelaporan',
            'items' => [
                ['key' => 'reports.index', 'label' => 'Ringkasan Laporan', 'path' => '/accounting/reports'],
                ['key' => 'reports.journals', 'label' => 'Jurnal Transaksi', 'path' => '/accounting/reports/journals'],
                ['key' => 'reports.trial-balance', 'label' => 'Neraca Saldo', 'path' => '/accounting/reports/trial-balance'],
                ['key' => 'reports.balance-sheet', 'label' => 'Neraca', 'path' => '/accounting/reports/balance-sheet'],
                ['key' => 'reports.income-statement', 'label' => 'Laba Rugi', 'path' => '/accounting/reports/income-statement'],
                ['key' => 'reports.cash-flow', 'label' => 'Arus Kas', 'path' => '/accounting/reports/cash-flow'],
                ['key' => 'reports.equity-change', 'label' => 'Perubahan Ekuitas', 'path' => '/accounting/reports/equity-change'],
                ['key' => 'reports.calk', 'label' => 'CALK', 'path' => '/accounting/reports/calk'],
                ['key' => 'reports.general-ledger', 'label' => 'Buku Besar', 'path' => '/accounting/reports/general-ledger'],
                ['key' => 'reports.simpanan', 'label' => 'Daftar Simpanan', 'path' => '/accounting/reports/simpanan'],
                ['key' => 'lending.reports.portfolio', 'label' => 'Portofolio Pinjaman', 'path' => '/lending/reports/portfolio'],
                ['key' => 'lending.reports.schedule-vs-actual', 'label' => 'Rencana vs Realisasi', 'path' => '/lending/reports/schedule-vs-actual'],
                ['key' => 'lending.reports.schedule-vs-actual-individu', 'label' => 'Rencana & Realisasi Individu', 'path' => '/lending/reports/schedule-vs-actual-individu'],
                ['key' => 'lending.reports.lpp-desa', 'label' => 'LPP Rekap Desa', 'path' => '/lending/reports/lpp-desa'],
                ['key' => 'lending.reports.lpp-desa-individu', 'label' => 'LPP Rekap Desa Individu', 'path' => '/lending/reports/lpp-desa-individu'],
                ['key' => 'lending.reports.lpp-kelompok', 'label' => 'LPP Rincian Kelompok', 'path' => '/lending/reports/lpp-kelompok'],
                ['key' => 'lending.reports.lpp-individu', 'label' => 'LPP Rincian Individu', 'path' => '/lending/reports/lpp-individu'],
                ['key' => 'lending.reports.kolek-desa', 'label' => 'Kolektibilitas Rekap Desa', 'path' => '/lending/reports/kolek-desa'],
                ['key' => 'lending.reports.kolek-kelompok', 'label' => 'Kolektibilitas Rincian Kelompok', 'path' => '/lending/reports/kolek-kelompok'],
                ['key' => 'lending.reports.kolek-desa-individu', 'label' => 'Kolektibilitas Rekap Desa Individu', 'path' => '/lending/reports/kolek-desa-individu'],
                ['key' => 'lending.reports.kolek-individu', 'label' => 'Kolektibilitas Rincian Individu', 'path' => '/lending/reports/kolek-individu'],
                ['key' => 'lending.reports.weekly.lpp-individu', 'label' => 'LPP Individu Mingguan', 'path' => '/lending/reports/weekly/lpp-individu'],
                ['key' => 'lending.reports.weekly.lpp-kelompok', 'label' => 'LPP Kelompok Mingguan', 'path' => '/lending/reports/weekly/lpp-kelompok'],
                ['key' => 'lending.reports.weekly.kolek-individu', 'label' => 'Kolektibilitas Individu Mingguan', 'path' => '/lending/reports/weekly/kolek-individu'],
                ['key' => 'lending.reports.weekly.kolek-kelompok', 'label' => 'Kolektibilitas Kelompok Mingguan', 'path' => '/lending/reports/weekly/kolek-kelompok'],
                ['key' => 'lending.reports.cadangan-penghapusan', 'label' => 'Cadangan Penghapusan (CKPN)', 'path' => '/lending/reports/cadangan-penghapusan'],
                ['key' => 'lending.reports.cadangan-penghapusan-individu', 'label' => 'CKPN Pinjaman Individu', 'path' => '/lending/reports/cadangan-penghapusan-individu'],
                ['key' => 'lending.reports.proposals', 'label' => 'Daftar Proposal', 'path' => '/lending/reports/proposals'],
                ['key' => 'lending.reports.verifications', 'label' => 'Daftar Verifikasi', 'path' => '/lending/reports/verifications'],
                ['key' => 'lending.reports.waiting-list', 'label' => 'Daftar Waiting List', 'path' => '/lending/reports/waiting-list'],
                ['key' => 'lending.reports.paid', 'label' => 'Daftar Pinjaman Lunas', 'path' => '/lending/reports/paid'],
                ['key' => 'lending.reports.write-offs', 'label' => 'Pinjaman Dihapusbukukan', 'path' => '/lending/reports/write-offs'],
                ['key' => 'lending.reports.write-offs-individu', 'label' => 'Pinjaman Dihapusbukukan Individu', 'path' => '/lending/reports/write-offs-individu'],
                ['key' => 'lending.reports.write-offs.beneficiaries', 'label' => 'Anggota Hapus Buku Kelompok', 'path' => '/lending/reports/write-offs/beneficiaries'],
                ['key' => 'lending.reports.due-today', 'label' => 'Tagihan Jatuh Tempo Hari Ini', 'path' => '/lending/reports/due-today'],
                ['key' => 'lending.reports.overdue', 'label' => 'Daftar Tunggakan', 'path' => '/lending/reports/overdue'],
                ['key' => 'lending.reports.near-settlement', 'label' => 'Pelunasan 3 Bulan ke Depan', 'path' => '/lending/reports/near-settlement'],
                ['key' => 'lending.reports.groups.active', 'label' => 'Kelompok Aktif', 'path' => '/lending/reports/groups/active'],
                ['key' => 'lending.reports.members.active', 'label' => 'Individu Aktif', 'path' => '/lending/reports/members/active'],
                ['key' => 'lending.reports.beneficiaries.active', 'label' => 'Pemanfaat Aktif', 'path' => '/lending/reports/beneficiaries/active'],
                ['key' => 'regulatory.ojk.cover', 'label' => 'OJK - Cover', 'path' => '/regulatory/ojk/cover'],
                ['key' => 'regulatory.ojk.profile', 'label' => 'OJK - Profil', 'path' => '/regulatory/ojk/profile'],
                ['key' => 'regulatory.ojk.balance-sheet', 'label' => 'OJK - Neraca', 'path' => '/regulatory/ojk/balance-sheet'],
                ['key' => 'regulatory.ojk.income-statement', 'label' => 'OJK - Laba Rugi', 'path' => '/regulatory/ojk/income-statement'],
                ['key' => 'regulatory.ojk.active-loans', 'label' => 'OJK - Rincian Pinjaman Aktif', 'path' => '/regulatory/ojk/active-loans'],
                ['key' => 'regulatory.ojk.paid-group', 'label' => 'OJK - Pinjaman Lunas Kelompok', 'path' => '/regulatory/ojk/paid-group'],
                ['key' => 'regulatory.ojk.paid-individu', 'label' => 'OJK - Pinjaman Lunas Individu', 'path' => '/regulatory/ojk/paid-individu'],
                ['key' => 'regulatory.ojk.savings', 'label' => 'OJK - Rincian Tabungan', 'path' => '/regulatory/ojk/savings'],
                ['key' => 'regulatory.ojk.loans-received', 'label' => 'OJK - Rincian Pinjaman Diterima', 'path' => '/regulatory/ojk/loans-received'],
                ['key' => 'regulatory.ojk.collectibility', 'label' => 'OJK - Kolektibilitas', 'path' => '/regulatory/ojk/collectibility'],
                ['key' => 'regulatory.ojk.collectibility-v2', 'label' => 'OJK - Kolektibilitas v2', 'path' => '/regulatory/ojk/collectibility-v2'],
                ['key' => 'regulatory.ojk.allowance', 'label' => 'OJK - Penyisihan Cadangan', 'path' => '/regulatory/ojk/allowance'],
                ['key' => 'regulatory.ojk.health-41', 'label' => 'OJK - Tingkat Kesehatan (POJK 41)', 'path' => '/regulatory/ojk/health'],
                ['key' => 'regulatory.ojk.interest', 'label' => 'OJK - Rincian Bunga', 'path' => '/regulatory/ojk/interest'],
                ['key' => 'regulatory.ojk.savings-receivables', 'label' => 'OJK - Simpanan Piutang', 'path' => '/regulatory/ojk/savings-receivables'],
            ],
        ],
        [
            'key' => 'tagihan',
            'label' => 'Tagihan',
            'items' => [
                ['key' => 'billing.invoices', 'label' => 'Daftar Tagihan', 'path' => '/billing/invoices'],
            ],
        ],
        [
            'key' => 'pengguna',
            'label' => 'Pengguna',
            'items' => [
                ['key' => 'access.roles', 'label' => 'Manajemen Role', 'path' => '/access/roles'],
                ['key' => 'access.users', 'label' => 'Manajemen User', 'path' => '/access/users'],
            ],
        ],
        [
            'key' => 'website',
            'label' => 'Website',
            'items' => [
                ['key' => 'website.posts', 'label' => 'Berita', 'path' => '/website/posts'],
                ['key' => 'website.pages', 'label' => 'Halaman', 'path' => '/website/pages'],
                ['key' => 'website.settings', 'label' => 'Pengaturan Situs', 'path' => '/website/settings'],
                ['key' => 'website.messages', 'label' => 'Pesan Masuk', 'path' => '/website/messages'],
            ],
        ],
        [
            'key' => 'notifikasi',
            'label' => 'Notifikasi',
            'items' => [
                ['key' => 'notifications.billing', 'label' => 'Billing Notice', 'path' => '/notifications/billing'],
            ],
        ],
        [
            'key' => 'portal',
            'label' => 'Anggota',
            'items' => [
                ['key' => 'portal.index', 'label' => 'Portal Saya', 'path' => '/portal'],
            ],
        ],
    ],
];
