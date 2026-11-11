#!/usr/bin/env node
/**
 * Ganti locale format tanggal hard-coded ('id-ID') menjadi locale aktif.
 *
 * Hanya menyentuh formatter TANGGAL. Format angka & mata uang tetap 'id-ID'
 * (Rupiah) agar konsisten dengan parser input dan data yang dikirim ke server.
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const JS_DIR = path.join(ROOT, 'resources', 'js');
const I18N_DIR = path.join(JS_DIR, 'i18n');

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (full === I18N_DIR) continue;
            walk(full, out);
        } else if (entry.isFile() && /\.(vue|js)$/.test(full)) {
            out.push(full);
        }
    }
    return out;
}

const DATE_OPTION_HINTS = /\b(dateStyle|timeStyle|day:|month:|year:|hour:|minute:|weekday:|timeZoneName)\b/;

function localize(source, file) {
    let changed = false;
    let out = source;

    // 1) Intl.DateTimeFormat('id-ID' -> Intl.DateTimeFormat(dateLocale()
    out = out.replace(/Intl\.DateTimeFormat\(\s*'id-ID'/g, () => {
        changed = true;
        return 'Intl.DateTimeFormat(dateLocale()';
    });

    // 2) toLocaleDateString('id-ID'
    out = out.replace(/toLocaleDateString\(\s*'id-ID'/g, () => {
        changed = true;
        return 'toLocaleDateString(dateLocale()';
    });

    // 3) toLocaleString('id-ID', { ...opsi tanggal... })
    out = out.replace(/toLocaleString\(\s*'id-ID'(\s*,\s*\{[^}]*\})/g, (match, options) => {
        if (!DATE_OPTION_HINTS.test(options)) return match;
        changed = true;
        return `toLocaleString(dateLocale()${options}`;
    });

    // 4) toLocaleString('id-ID') tanpa opsi, hanya jika jelas tanggal.
    out = out.replace(/(new Date\([^)]*\)\s*\.toLocaleString\(\s*)'id-ID'/g, (match, prefix) => {
        changed = true;
        return `${prefix}dateLocale()`;
    });

    // 5) Baris dengan field timestamp + toLocaleString('id-ID')
    out = out.replace(/^.*$/gm, (line) => {
        if (!/toLocaleString\(\s*'id-ID'\s*\)/.test(line)) return line;
        if (!/(_at|created|updated|submitted|acknowledged|started|finished|date|tanggal|time|waktu)/i.test(line)) return line;
        changed = true;
        return line.replace(/toLocaleString\(\s*'id-ID'/g, 'toLocaleString(dateLocale()');
    });

    if (!changed) return null;

    // Tambahkan import kalau belum ada.
    if (!/\bdateLocale\b[^\n]*from\s+['"][^'"]*i18n/.test(out) && !/\bimport\s*\{[^}]*\bdateLocale\b[^}]*\}\s*from/.test(out)) {
        let rel = path.relative(path.dirname(file), I18N_DIR).replace(/\\/g, '/');
        if (!rel.startsWith('.')) rel = `./${rel}`;
        const importLine = `import { dateLocale } from '${rel}';\n`;

        const setup = out.match(/<script setup[^>]*>\s*\n/);
        if (setup) {
            const at = setup.index + setup[0].length;
            // hindari import ganda jika file sudah mengimpor dari i18n
            const existing = out.match(/import\s*\{([^}]*)\}\s*from\s*['"]([^'"]*i18n)['"];?\n/);
            if (existing) {
                const names = existing[1].split(',').map((s) => s.trim()).filter(Boolean);
                if (!names.includes('dateLocale')) {
                    const merged = [...names, 'dateLocale'].join(', ');
                    return out.replace(existing[0], `import { ${merged} } from '${existing[2]}';\n`);
                }
                return out;
            }
            return `${out.slice(0, at)}${importLine}${out.slice(at)}`;
        }

        const jsImport = /^(import[^\n]*\n)/m;
        if (jsImport.test(out)) {
            return out.replace(jsImport, `$1${importLine}`);
        }
        return `${importLine}${out}`;
    }

    return out;
}

const files = walk(JS_DIR);
const changedFiles = [];

for (const file of files) {
    const source = fs.readFileSync(file, 'utf8');
    if (!source.includes('id-ID')) continue;
    const next = localize(source, file);
    if (next && next !== source) {
        fs.writeFileSync(file, next, 'utf8');
        changedFiles.push(path.relative(ROOT, file).replace(/\\/g, '/'));
    }
}

console.log(`File diubah: ${changedFiles.length}`);
for (const file of changedFiles) console.log(`  ${file}`);
