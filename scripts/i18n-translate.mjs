#!/usr/bin/env node
/**
 * Terjemahkan key i18n yang belum ada di kamus.
 *
 * - Override kurasi (glosarium domain) dipakai lebih dulu agar konsisten dengan
 *   istilah yang sudah ada di kamus (mis. "Pokok" -> "Principal").
 * - Sisanya diterjemahkan otomatis per-batch lalu disimpan bertahap ke
 *   storage/app/i18n-translations.json (aman dijalankan ulang / resume).
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const MISSING_FILE = path.join(ROOT, 'storage', 'app', 'i18n-missing.json');
const OUT_FILE = path.join(ROOT, 'storage', 'app', 'i18n-translations.json');

/** Kurasi istilah domain & label umum (menjaga konsistensi). */
const OVERRIDES = {
    // Siklus pinjaman
    'Pokok': 'Principal',
    'Jasa': 'Interest',
    'Bunga': 'Interest',
    'Denda': 'Penalty',
    'Sisa Pokok': 'Remaining Principal',
    'Total Pokok': 'Total Principal',
    'Saldo Pokok': 'Principal Balance',
    'Pokok Pinjaman': 'Loan Principal',
    'Realisasi Pokok': 'Principal Realization',
    'Rencana Pokok': 'Planned Principal',
    'Tunggakan': 'Arrears',
    'Tunggakan Pokok': 'Principal Arrears',
    'Tunggakan Jasa': 'Interest Arrears',
    'Jatuh Tempo': 'Due Date',
    'Tanggal Cair': 'Disbursement Date',
    'Tgl Cair': 'Disbursement Date',
    'Tgl Lunas': 'Settled Date',
    'Tanggal Verifikasi': 'Verification Date',
    'Tanggal Penetapan': 'Determination Date',
    'Tanggal Terbit': 'Issue Date',
    'Tanggal Selesai': 'Completion Date',
    'Pencairan': 'Disbursement',
    'Angsuran': 'Installment',
    'Pinjaman': 'Loan',
    'Pinjaman Aktif': 'Active Loans',
    'Jumlah Pinjaman': 'Loan Amount',
    'Nomor Pinjaman': 'Loan Number',
    'Peminjam': 'Borrower',
    'Peminjam / Kelompok': 'Borrower / Group',
    'Plafon': 'Limit',
    'Plafon Pinjaman': 'Loan Limit',
    'Realisasi': 'Realization',
    'Lunas': 'Paid Off',
    'Ditutup': 'Closed',
    'Cair': 'Disbursed',
    'Verifikasi': 'Verification',
    'Proposal': 'Proposal',
    'Pantau': 'Monitor',
    'Rekening': 'Account',
    'Jenis Simpanan': 'Savings Type',
    'Simpanan': 'Savings',
    'Setoran': 'Deposit',
    'Penarikan': 'Withdrawal',

    // Keuangan / akuntansi
    'Saldo': 'Balance',
    'Saldo Awal': 'Opening Balance',
    'Saldo Akhir': 'Closing Balance',
    'Total Aset': 'Total Assets',
    'Total Outstanding': 'Total Outstanding',
    'Total Pendapatan': 'Total Revenue',
    'Total Dibayar': 'Total Paid',
    'Total': 'Total',
    'Kas & Bank': 'Cash & Bank',
    'Pajak': 'Tax',
    'Selisih': 'Difference',
    'Tahun Buku': 'Fiscal Year',
    'Cadangan Dibentuk': 'Provision Formed',
    'Alokasi': 'Allocation',
    'Target': 'Target',
    'Realisasi Pokok': 'Principal Realization',
    'Real Bulan Ini': 'Actual This Month',
    'Real Kumulatif': 'Cumulative Actual',
    'Real s.d. Lalu': 'Actual Up to Last Period',
    'Sisa Jasa': 'Remaining Interest',
    'Grace Pokok': 'Principal Grace',
    'Grace Jasa': 'Interest Grace',
    'Total Bunga': 'Total Interest',
    'Total Kredit': 'Total Credit',
    'Total Debit': 'Total Debit',

    // Master data
    'Desa': 'Village',
    'Anggota': 'Member',
    'Kelompok': 'Group',
    'Lembaga': 'Institution',
    'Nama Lembaga': 'Institution Name',
    'Identitas Lembaga': 'Institution Identity',
    'Nama Kelompok': 'Group Name',
    'Nama Lengkap': 'Full Name',
    'Ketua': 'Chairperson',
    'Pengawas': 'Supervisor',
    'Alamat': 'Address',
    'Nomor': 'Number',
    'No': 'No.',
    'Kode': 'Code',
    'Jenis': 'Type',
    'Tipe': 'Type',
    'Deskripsi': 'Description',
    'Catatan': 'Notes',
    'Keterangan': 'Remarks',
    'Judul': 'Title',
    'Slug': 'Slug',
    'Logo Lembaga': 'Institution Logo',
    'Foto KTP anggota': 'Member ID Card Photo',
    'Metode': 'Method',
    'Keperluan': 'Purpose',
    'Waktu': 'Time',
    'Aktivitas': 'Activity',
    'Persona': 'Persona',
    'Pengaturan': 'Settings',

    // Navigasi & aksi umum
    'Bulan': 'Month',
    'Tahun': 'Year',
    'Hari': 'Day',
    'Minggu': 'Week',
    'Mingguan': 'Weekly',
    'Bulanan': 'Monthly',
    'Tahunan': 'Annual',
    'Semua': 'All',
    'Semua Produk': 'All Products',
    'Semua status': 'All statuses',
    'Semua Kecamatan (Gabungan)': 'All Districts (Combined)',
    'Lainnya': 'Others',
    'Cetak': 'Print',
    'Unduh PDF': 'Download PDF',
    'Tampilkan': 'Show',
    'Buka di tab baru': 'Open in new tab',
    'Masukkan alasan': 'Enter a reason',
    'Masukkan catatan': 'Enter notes',
    'Masukkan nama': 'Enter name',
    'Masukkan referensi': 'Enter reference',
    'Memuat…': 'Loading…',
    'Memuat...': 'Loading...',
    'Cari anggota...': 'Search members...',
    'Cari pinjaman...': 'Search loans...',
    'Pilih akun': 'Select account',
    'Perhatian': 'Attention',
    'Selanjutnya': 'Next',
    'Sebelumnya': 'Previous',
    'Ya, Hapus': 'Yes, Delete',
    'Kembalikan ke Proposal': 'Return to Proposal',
    'Gagal membangun laporan:': 'Failed to build report:',
    'Tidak ada pinjaman individu aktif pada periode ini.': 'No active individual loans in this period.',
    'Tidak ada pinjaman kelompok aktif pada periode ini.': 'No active group loans in this period.',
    'Tidak ada pinjaman dihapusbukukan': 'No written-off loans',
    'Periode Mingguan Aktif:': 'Active Weekly Period:',
    'Aturan kolek aktif:': 'Active collectibility rules:',
    'Minggu ke-1 (tgl 1–7)': 'Week 1 (1st–7th)',
    'Minggu ke-2 (tgl 8–14)': 'Week 2 (8th–14th)',
    'Minggu ke-3 (tgl 15–21)': 'Week 3 (15th–21st)',
    'Minggu ke-4 (tgl 22–28)': 'Week 4 (22nd–28th)',
    'Minggu ke-5 (tgl 29–akhir bulan)': 'Week 5 (29th–end of month)',
    'Pembulatan': 'Rounding',
    'Pelaporan Pinjaman': 'Loan Reporting',
    'Pelaporan Lending — Mingguan': 'Lending Reporting — Weekly',
    'Laporan Piutang': 'Receivables Report',
    'Riwayat pinjaman': 'Loan history',
    'Alasan Penghapusan': 'Deletion Reason',
    'Tidak ada jatuh tempo': 'No due dates',
    'Tidak ada run gagal': 'No failed runs',
    'Breadcrumb': 'Breadcrumb',
    'Default': 'Default',
    'Waiting': 'Waiting',
    'Production (Live Transaction)': 'Production (Live Transaction)',
    'Sandbox (Pengujian / Test Mode)': 'Sandbox (Test Mode)',
    'OJK · Regulatory': 'OJK · Regulatory',
    'Pelaporan · Tutup Buku': 'Reporting · Period Close',
    'Username': 'Username',
    'Next': 'Next',
    'Preview': 'Preview',
    'Progress': 'Progress',
    'Coverage PPAP': 'PPAP Coverage',
    'Breadcrumb': 'Breadcrumb',
    'Facebook': 'Facebook',
    'Instagram': 'Instagram',
    'YouTube': 'YouTube',

    // Bulan
    'Januari': 'January',
    'Februari': 'February',
    'Maret': 'March',
    'April': 'April',
    'Mei': 'May',
    'Juni': 'June',
    'Juli': 'July',
    'Agustus': 'August',
    'September': 'September',
    'Oktober': 'October',
    'November': 'November',
    'Desember': 'December',
};

const MISSING = JSON.parse(fs.readFileSync(MISSING_FILE, 'utf8'));
const existing = fs.existsSync(OUT_FILE) ? JSON.parse(fs.readFileSync(OUT_FILE, 'utf8')) : {};
const results = { ...existing };

for (const [key, value] of Object.entries(OVERRIDES)) {
    if (!(key in results)) results[key] = value;
}

const pending = MISSING.map((item) => item.key).filter((key) => !(key in results));
console.log(`Total belum: ${MISSING.length}; override: ${Object.keys(OVERRIDES).length}; sisa mesin: ${pending.length}`);

const ENDPOINT = 'https://translate.googleapis.com/translate_a/single';

async function translateBatch(lines) {
    const body = new URLSearchParams({ client: 'gtx', sl: 'id', tl: 'en', dt: 't', q: lines.join('\n') });
    const response = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body,
    });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const data = await response.json();
    const translated = (data?.[0] || []).map((segment) => segment?.[0] || '').join('');
    return translated.split('\n');
}

function cleanup(text) {
    return text
        .replace(/\u00a0/g, ' ')
        .replace(/\s+$/g, '')
        .replace(/^Save changes$/i, 'Save Changes');
}

let done = 0;
let failures = 0;

for (let i = 0; i < pending.length; ) {
    const batch = [];
    let size = 0;
    while (i < pending.length && batch.length < 40) {
        const key = pending[i];
        if (size + key.length > 1400 && batch.length > 0) break;
        batch.push(key);
        size += key.length + 1;
        i += 1;
    }

    try {
        const lines = await translateBatch(batch);
        if (lines.length !== batch.length) {
            // fallback satu per satu
            for (const key of batch) {
                const [single] = await translateBatch([key]);
                results[key] = cleanup(single ?? key);
                done += 1;
            }
        } else {
            batch.forEach((key, index) => {
                results[key] = cleanup(lines[index]);
                done += 1;
            });
        }
    } catch (error) {
        failures += batch.length;
        console.error(`Gagal batch @${i}: ${error.message}`);
    }

    if (done % 200 < 40) {
        fs.writeFileSync(OUT_FILE, JSON.stringify(results, null, 2), 'utf8');
        console.log(`  progres: ${done} baru, ${Object.keys(results).length} total`);
    }
    await new Promise((resolve) => setTimeout(resolve, 120));
}

fs.writeFileSync(OUT_FILE, JSON.stringify(results, null, 2), 'utf8');
console.log(`Selesai. Baru: ${done}, gagal: ${failures}, total tersimpan: ${Object.keys(results).length}`);
console.log(`Output: ${OUT_FILE}`);
