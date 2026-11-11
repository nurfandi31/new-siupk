#!/usr/bin/env node
/**
 * Ekstrak semua key `t('...')` dari frontend lalu bandingkan dengan kamus EN.
 * Output: storage/app/i18n-missing.json ({ key, count } urut frekuensi).
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const JS_DIR = path.join(ROOT, 'resources', 'js');
const I18N_FILE = path.join(JS_DIR, 'i18n', 'index.js');

function loadDictionaryKeys() {
    const source = fs.readFileSync(I18N_FILE, 'utf8');
    const start = source.indexOf('const en = {');
    const end = source.indexOf('\n};', start);
    const body = source.slice(start, end === -1 ? undefined : end);
    const keys = new Set();
    const re = /^\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")\s*:\s*(?:'|")/gm;
    let m;
    while ((m = re.exec(body)) !== null) {
        keys.add((m[1] ?? m[2]).replace(/\\(['"\\])/g, '$1'));
    }
    return keys;
}

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, out);
        else if (entry.isFile() && /\.(vue|js)$/.test(full)) out.push(full);
    }
    return out;
}

const dictionary = loadDictionaryKeys();
const counts = new Map();

for (const file of walk(JS_DIR)) {
    if (path.resolve(file) === path.resolve(I18N_FILE)) continue;
    const source = fs.readFileSync(file, 'utf8');
    const re = /\bt\(\s*'((?:[^'\\\n]|\\.)*)'/g;
    let m;
    while ((m = re.exec(source)) !== null) {
        const key = m[1].replace(/\\(['"\\])/g, '$1');
        counts.set(key, (counts.get(key) || 0) + 1);
    }
}

const missing = [...counts.entries()]
    .filter(([key]) => !dictionary.has(key))
    .sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))
    .map(([key, count]) => ({ key, count }));

fs.writeFileSync(path.join(ROOT, 'storage', 'app', 'i18n-missing.json'), JSON.stringify(missing, null, 2), 'utf8');

console.log(`Total key t(): ${counts.size}`);
console.log(`Sudah ada di kamus: ${counts.size - missing.length}`);
console.log(`Belum ada: ${missing.length}`);
console.log(`Total karakter belum diterjemahkan: ${missing.reduce((sum, x) => sum + x.key.length, 0)}`);
