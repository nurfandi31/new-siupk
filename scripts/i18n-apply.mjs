#!/usr/bin/env node
/**
 * Tulis hasil terjemahan (storage/app/i18n-translations.json) ke kamus EN di
 * resources/js/i18n/index.js, setelah normalisasi istilah domain.
 *
 * Idempotent: key yang sudah ada di kamus tidak ditimpa/diduplikasi.
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const I18N_FILE = path.join(ROOT, 'resources', 'js', 'i18n', 'index.js');
const TRANSLATIONS_FILE = path.join(ROOT, 'storage', 'app', 'i18n-translations.json');

const translations = JSON.parse(fs.readFileSync(TRANSLATIONS_FILE, 'utf8'));

/** Koreksi istilah yang salah diterjemahkan mesin (konteks domain koperasi). */
const FIXES = {
    'No. Pinjaman': 'Loan No.',
    'No. Registrasi': 'Registration No.',
    'No. Jurnal': 'Journal No.',
    'No. Kontrak': 'Contract No.',
    'No. HP': 'Phone No.',
    'No. Rekening': 'Account No.',
    'No. Anggota': 'Member No.',
    'No. Urut': 'Seq. No.',
    'Tutup modal': 'Close dialog',
    'Tutup Modal': 'Close dialog',
    'Gap Jasa': 'Interest Gap',
    'Gap Pokok': 'Principal Gap',
    'Grace Period Jasa': 'Interest Grace Period',
    'Grace Jasa (bulan)': 'Interest Grace (months)',
    'Grace Pokok (bulan)': 'Principal Grace (months)',
    'Grace Jasa': 'Interest Grace',
    'Grace Pokok': 'Principal Grace',
    'Sistem Angs. Jasa': 'Interest Installment System',
    'Sistem Angs. Pokok': 'Principal Installment System',
    'Tungg. Jasa': 'Interest Arrears',
    'Tungg. Pokok': 'Principal Arrears',
    'Frek. Jasa': 'Interest Freq.',
    'Frek. Pokok': 'Principal Freq.',
    'Freq. Jasa': 'Interest Freq.',
    'Freq. Pokok': 'Principal Freq.',
    'Σ Jasa': 'Σ Interest',
    'Σ Pokok': 'Σ Principal',
    'Sum Jasa': 'Interest Sum',
    'Sum Pokok': 'Principal Sum',
    'Dasar Bunga': 'Interest Base',
    'Metode bunga': 'Interest method',
    'Rincian Bunga': 'Interest Details',
    'Tanggal bunga': 'Interest Date',
    'Bunga / Bulan': 'Interest / Month',
    'Bunga & jenis': 'Interest & types',
    'Bunga ✓': 'Interest ✓',
    'Dalam proses': 'In progress',
    'Selesai': 'Completed',
    'Tidak diketahui': 'Unknown',
    'Tutup buku': 'Period close',
    'Sisi AKTIVA — Piutang': 'ASSETS side — Receivables',
    'Sisi PASIVA — Simpanan': 'LIABILITIES side — Savings',
    'Rekening Modal': 'Capital Account',
    'Perubahan Modal': 'Changes in Capital',

    // Audit UI 2026-11-11 lanjutan
    'S.d. Triwulan Berjalan': 'Up to Current Quarter',
    'Sinkron': 'Sync',
    'Tenant Next ketemu': 'Tenant Next found',
    'Tagihan siupk': 'siupk Billing',
    'Cadangan': 'Backup',
    'Sudah dibaca': 'Read',
    'Tertinggal': 'Missing',
    'Edit Anggota': 'Edit Member',
    'Buat Role': 'Create Role',
    'Tanpa role': 'No role',
    'Tanpa plan': 'No plan',
    'Kredensial Aktif': 'Credentials Active',
    'Sisa kirim ulang:': 'Resend remaining:',
    'akun siap dipakai': 'accounts ready to use',
    'Header': 'Header',
    'Akun :name tidak dapat digunakan untuk login.': 'Account :name cannot be used to sign in.',
    'Masuk ke Akun Anda': 'Sign in to Your Account',
    'masuk ke dashboard': 'sign in to dashboard',
    'Kembali ke halaman masuk': 'Back to sign-in page',
    'Login manual': 'Manual sign-in',
};

function keepCase(sample, replacement) {
    if (sample === sample.toUpperCase() && sample.length > 1) return replacement.toUpperCase();
    if (sample[0] === sample[0]?.toUpperCase()) return replacement[0].toUpperCase() + replacement.slice(1);
    return replacement;
}

function replaceWord(text, pattern, replacement) {
    return text.replace(pattern, (match) => keepCase(match, replacement));
}

const PROPER_JASA = /Otoritas Jasa Keuangan|Lembaga Jasa Keuangan|Jasa Keuangan/;

function normalize(source, output) {
    if (FIXES[source]) return FIXES[source];
    let text = output;

    if (/bunga/i.test(source)) {
        text = replaceWord(text, /\bflowers?\b/gi, 'interest');
        text = replaceWord(text, /\bmoons?\b/gi, 'month');
        text = text.replace(/Interest Basics/gi, 'Interest Base');
    }

    if (/jasa/i.test(source) && !PROPER_JASA.test(source)) {
        text = replaceWord(text, /\bservices?\b/gi, 'interest');
        text = text.replace(/Interest Installment(s)?/g, 'Interest Installment$1');
    }

    if (/\bpokok\b/i.test(source)) {
        text = replaceWord(text, /\btrees?\b/gi, 'principal');
        text = text.replace(/\bPrincipal Basics\b/g, 'Principal Base');
    }

    if (/pemanfaat/i.test(source)) {
        text = replaceWord(text, /\busers\b/gi, 'beneficiaries');
        text = replaceWord(text, /\buser\b/gi, 'beneficiary');
        text = replaceWord(text, /\bbenefits\b/gi, 'beneficiaries');
        text = replaceWord(text, /\bbenefit\b/gi, 'beneficiary');
        text = replaceWord(text, /\butilizers?\b/gi, 'beneficiary');
        text = text.replace(/List of Beneficiaries( & Allocations)?/g, 'Beneficiary List$1');
        text = text.replace(/Number of Beneficiaries/gi, 'Number of Beneficiaries');
    }

    if (/plafon/i.test(source)) {
        text = replaceWord(text, /\bceilings?\b/gi, 'limit');
    }

    if (/simpanan/i.test(source)) {
        text = replaceWord(text, /\bsaved\b/gi, 'savings');
        text = replaceWord(text, /\bsave\b/gi, 'savings');
        text = replaceWord(text, /\bstorage\b/gi, 'savings');
    }

    if (/kolekt[ai]bilitas/i.test(source)) {
        text = text.replace(/collectability/gi, (m) => keepCase(m, 'collectibility'));
    }

    if (/minggu/i.test(source)) {
        text = replaceWord(text, /\bsundays?\b/gi, 'week');
    }

    if (/bulan/i.test(source)) {
        text = replaceWord(text, /\bmoons?\b/gi, 'month');
    }

    // "3rd/5th" dsb. pada label "Angsuran ke-N"
    text = text.replace(/\bInsert After (\d+)(?:st|nd|rd|th) Installment\b/gi, 'Insert After Installment #$1');

    return text.trim();
}

function escapeKey(value) {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function escapeValue(value) {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r?\n/g, ' ');
}

const source = fs.readFileSync(I18N_FILE, 'utf8');
const start = source.indexOf('const en = {');
const end = source.indexOf('\n};', start);
if (start === -1 || end === -1) throw new Error('Blok kamus EN tidak ditemukan');

const dictionaryBody = source.slice(start, end);
const existing = new Set();
const re = /^\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")\s*:/gm;
let m;
while ((m = re.exec(dictionaryBody)) !== null) {
    existing.add((m[1] ?? m[2]).replace(/\\(['"\\])/g, '$1'));
}

const entries = [];
for (const [sourceKey, raw] of Object.entries(translations)) {
    if (existing.has(sourceKey)) continue;
    const value = normalize(sourceKey, raw);
    if (!value) continue;
    entries.push([sourceKey, value]);
}

entries.sort((a, b) => a[0].localeCompare(b[0], 'id'));

const lines = entries.map(([key, value]) => `    '${escapeKey(key)}': '${escapeValue(value)}',`).join('\n');

if (lines.length === 0) {
    console.log('Tidak ada entri baru.');
    process.exit(0);
}

const insertion = `\n    // ===== Tambahan otomatis (audit UI 2026-11-11) =====\n${lines}\n`;

const next = source.slice(0, end) + insertion + source.slice(end);
fs.writeFileSync(I18N_FILE, next, 'utf8');

console.log(`Entri baru ditambahkan: ${entries.length}`);
console.log(`Total baris kamus sekarang: ${next.split('\n').length}`);
