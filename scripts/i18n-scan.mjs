#!/usr/bin/env node
/**
 * Audit + auto-wrap utilitas i18n untuk frontend Vue/Inertia.
 *
 * Strategi project ini: locale sumber adalah Bahasa Indonesia, dan kamus
 * `resources/js/i18n/index.js` memetakan teks Indonesia -> Inggris.
 * Scanner ini mencari teks UI statis yang belum dibungkus `t()` lalu
 * (opsional dengan --write) membungkusnya otomatis.
 *
 * Pemakaian:
 *   node scripts/i18n-scan.mjs                  # ringkasan audit
 *   node scripts/i18n-scan.mjs --json report.json
 *   node scripts/i18n-scan.mjs --write           # terapkan pembungkusan t()
 *   node scripts/i18n-scan.mjs --write --only=Pages/Lending,Layouts
 *
 * Yang TIDAK disentuh (sesuai batasan: data dari database tidak diterjemahkan):
 *   - `{{ ekspresi }}` (nama anggota, nominal, dsb.)
 *   - atribut dinamis/`v-bind`
 *   - teks di <pre>, <code>, <script>, <style>
 *   - nilai teknis (class, icon name, url, tipe, varian)
 */
import fs from 'node:fs';
import path from 'node:path';

const ROOT = path.resolve(import.meta.dirname, '..');
const JS_DIR = path.join(ROOT, 'resources', 'js');
const I18N_FILE = path.join(JS_DIR, 'i18n', 'index.js');

const args = process.argv.slice(2);
const WRITE = args.includes('--write');
const JSON_OUT = (() => {
    const hit = args.find((a) => a.startsWith('--json='));
    return hit ? path.resolve(ROOT, hit.slice('--json='.length)) : null;
})();
const ONLY = (() => {
    const hit = args.find((a) => a.startsWith('--only='));
    if (!hit) return null;
    return hit
        .slice('--only='.length)
        .split(',')
        .map((s) => s.trim().replace(/\\/g, '/'))
        .filter(Boolean);
})();

/* ------------------------------------------------------------------ *
 * Kamus
 * ------------------------------------------------------------------ */

function loadDictionaryKeys() {
    const source = fs.readFileSync(I18N_FILE, 'utf8');
    const start = source.indexOf('const en = {');
    if (start === -1) return new Set();
    const end = source.indexOf('\n};', start);
    const body = source.slice(start, end === -1 ? undefined : end);
    const keys = new Set();
    const re = /^\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")\s*:\s*(?:'|")/gm;
    let match;
    while ((match = re.exec(body)) !== null) {
        keys.add(unescapeJs(match[1] ?? match[2]));
    }
    return keys;
}

function unescapeJs(value) {
    return value.replace(/\\(['"\\])/g, '$1').replace(/\\n/g, '\n').replace(/\\t/g, '\t');
}

function escapeJs(value) {
    return value.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/\r?\n/g, ' ');
}

/* ------------------------------------------------------------------ *
 * Utilitas teks
 * ------------------------------------------------------------------ */

const ENTITIES = {
    amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: '\u00a0',
    hellip: '\u2026', mdash: '\u2014', ndash: '\u2013', middot: '\u00b7',
    times: '\u00d7', laquo: '\u00ab', raquo: '\u00bb', copy: '\u00a9',
    rsquo: '\u2019', lsquo: '\u2018', ldquo: '\u201c', rdquo: '\u201d',
    deg: '\u00b0', bull: '\u2022', euro: '\u20ac', rarr: '\u2192', larr: '\u2190',
    uarr: '\u2191', darr: '\u2193', check: '\u2713', hyphen: '\u2010',
};

function decodeEntities(value) {
    return value.replace(/&(#x?[0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);/g, (full, name) => {
        if (name[0] === '#') {
            const code = name[1].toLowerCase() === 'x' ? parseInt(name.slice(2), 16) : parseInt(name.slice(1), 10);
            return Number.isFinite(code) ? String.fromCodePoint(code) : full;
        }
        return ENTITIES[name.toLowerCase()] ?? full;
    });
}

const HAS_LETTER = /[A-Za-z\u00c0-\u024f]/;

/** Apakah string layak diterjemahkan sebagai teks UI? */
function isTranslatableText(value) {
    const trimmed = value.trim();
    if (trimmed.length < 2) return false;
    if (!HAS_LETTER.test(trimmed)) return false;
    // Harus ada huruf kecil: membuang akronim/nilai seperti "IDR", "NPL", "OK".
    if (!/[a-z]/.test(trimmed)) return false;
    // Lewati ekspresi/kode yang tidak sengaja tertangkap.
    if (/[{}<>]/.test(trimmed)) return false;
    if (/=>|::|===|!==|\b(?:true|false|null|undefined)\b/.test(trimmed)) return false;
    if (/[A-Za-z0-9]_[A-Za-z0-9]/.test(trimmed)) return false;
    if (/\s=\s|^=|=$/.test(trimmed)) return false;
    if (/^(https?:\/\/|\/|\.\/|#)/.test(trimmed)) return false;
    // Lewati teks yang didominasi simbol/format (mis. "--", "N/A", "Rp 0").
    if (/^[\s\d\W]+$/.test(trimmed)) return false;
    if (/^\d[\d\s.,%:/-]*[A-Za-z]{0,3}$/.test(trimmed)) return false;
    // Lewati teks yang jelas berupa potongan kode/kelas.
    if (/^[a-z0-9:_\-/.]+$/.test(trimmed) && !/\s/.test(trimmed)) {
        // satu kata tanpa spasi: hanya terjemahkan kalau berawalan huruf kapital
        if (!/^[A-Z]/.test(trimmed)) return false;
    }
    return true;
}

/** Kata kunci yang sering muncul sebagai potongan kode, bukan teks UI. */
const TECHNICAL_EXACT = new Set([
    'true', 'false', 'null', 'undefined', 'auto', 'none', 'block', 'flex', 'grid',
    'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'ID', 'EN', 'CSS', 'HTML', 'JSON',
    'PDF', 'CSV', 'XLSX', 'N/A', 'OK', 'Rp', 'PT', 'CV', 'UD', 'SK', 'SPK', 'BA',
]);

function isTechnicalWord(value) {
    return TECHNICAL_EXACT.has(value.trim());
}

/* ------------------------------------------------------------------ *
 * Parser SFC
 * ------------------------------------------------------------------ */

const SCRIPT_RE = /<script\b[^>]*>[\s\S]*?<\/script>/gi;
const STYLE_RE = /<style\b[^>]*>[\s\S]*?<\/style>/gi;

function maskBlocks(source) {
    let masked = source;
    const mask = (re) => {
        masked = masked.replace(re, (match) => {
            const newlines = match.replace(/[^\n]/g, ' ');
            return newlines;
        });
    };
    mask(SCRIPT_RE);
    mask(STYLE_RE);
    return masked;
}

/** Cari rentang root <template> (bukan <template v-for/slot di dalamnya). */
function findRootTemplate(masked) {
    const openRe = /<template\b[^>]*>/g;
    const closeRe = /<\/template\s*>/g;
    let first = openRe.exec(masked);
    if (!first) return null;
    const start = first.index;
    const startTagEnd = first.index + first[0].length;
    let depth = 1;
    let cursor = startTagEnd;
    while (depth > 0) {
        openRe.lastIndex = cursor;
        closeRe.lastIndex = cursor;
        const nextOpen = openRe.exec(masked);
        const nextClose = closeRe.exec(masked);
        if (!nextClose) return null;
        if (nextOpen && nextOpen.index < nextClose.index) {
            depth += 1;
            cursor = nextOpen.index + nextOpen[0].length;
        } else {
            depth -= 1;
            const end = nextClose.index;
            cursor = nextClose.index + nextClose[0].length;
            if (depth === 0) return { start, startTagEnd, end };
        }
    }
    return null;
}

/** Daftar tag yang isinya tidak boleh diubah. */
const SKIP_CONTENT_TAGS = new Set(['pre', 'code', 'script', 'style', 'svg', 'math', 'samp', 'kbd']);
/** Tag yang isinya justru teks UI (walaupun biasanya dianggap mentah). */
const TEXT_TAGS = new Set(['option', 'textarea', 'title', 'button', 'a', 'label', 'span', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'th', 'dt', 'dd', 'legend', 'figcaption', 'small', 'strong', 'em', 'b', 'i', 'div']);

const TEXT_ATTRS = new Set([
    'placeholder', 'title', 'aria-label', 'aria-description', 'alt', 'label',
    'tooltip', 'description', 'hint', 'text', 'subtitle', 'caption', 'header',
    'empty-text', 'empty-message', 'no-data-text', 'search-placeholder',
    'confirm-text', 'cancel-text', 'confirm-label', 'cancel-label', 'help',
    'helper', 'message', 'heading',
]);

function parseAttrs(tagText) {
    // tagText: seluruh "<tag ...>" (tanpa < >)
    const attrs = [];
    const re = /([^\s"'<>/=]+)(?:\s*=\s*("([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
    // lewati nama tag
    const withoutName = tagText.replace(/^[a-zA-Z][\w:.-]*/, '');
    let match;
    while ((match = re.exec(withoutName)) !== null) {
        const name = match[1];
        const value = match[3] ?? match[4] ?? match[5];
        if (name === '') continue;
        attrs.push({ name, value, bounds: [match.index, match.index + match[0].length] });
    }
    return attrs;
}

/** Elemen yang dianggap "blok teks": evaluasi fragmentasi dilakukan di sini. */
const BLOCK_TAGS = new Set([
    'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'th', 'tr',
    'section', 'article', 'header', 'footer', 'aside', 'nav', 'main', 'form',
    'fieldset', 'legend', 'button', 'a', 'label', 'option', 'textarea', 'title',
    'dt', 'dd', 'figcaption', 'ul', 'ol', 'table', 'thead', 'tbody', 'tfoot',
    'blockquote', 'summary', 'dialog', 'caption', 'select', 'small', 'span',
]);

/** Elemen void: tidak pernah punya tag penutup. */
const VOID_TAGS = new Set([
    'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta',
    'param', 'source', 'track', 'wbr', 'path', 'circle', 'rect', 'line',
    'polyline', 'polygon', 'ellipse', 'use', 'stop', 'image',
]);

/**
 * Tokenisasi template menjadi daftar penggantian (tanpa mengubah source dulu).
 * offset dihitung terhadap source asli (bukan masked).
 *
 * Teks hanya diterjemahkan kalau ia satu-satunya fragmen teks di dalam blok
 * terdekat dan blok itu tidak mengandung interpolasi. Tujuannya agar kalimat
 * seperti "Telah {{ n }} hari" tidak diterjemahkan sepotong-sepotong.
 */
/**
 * Ambil literal string di dalam ekspresi mustache yang aman diterjemahkan.
 * Contoh yang didukung: `{{ 'Teks' }}`, `{{ a ? 'A' : 'B' }}`,
 * `{{ nilai || 'Default' }}`. Ekspresi yang memanggil fungsi (termasuk `t()`),
 * memakai konkatenasi, template literal, atau akses indeks tidak disentuh.
 */
function collectMustacheLiteralEdits(expr, exprStart, edits, texts) {
    const literalRe = /(['"])((?:[^'"\\\n]|\\\\.)*)\1/g;
    const literals = [];
    let m;
    while ((m = literalRe.exec(expr)) !== null) {
        literals.push({ match: m[0], quote: m[1], raw: m[2], index: m.index });
    }
    if (literals.length === 0) return;

    // Sisa ekspresi setelah literal dihapus harus hanya berisi operator/identifier.
    const rest = expr.replace(literalRe, '\u00a7');
    if (!/^[\s\w.$?!:&|()\u00a7]*$/.test(rest)) return;
    if (/[A-Za-z_$][\w$]*\s*\(/.test(rest)) return; // panggilan fungsi / t()

    for (const literal of literals) {
        const value = unescapeJs(literal.raw);
        if (!isTranslatableText(value)) continue;
        const key = value.trim().replace(/\s+/g, ' ');
        if (isTechnicalWord(key)) continue;
        const start = exprStart + literal.index;
        edits.push({
            start,
            end: start + literal.match.length,
            replacement: `t('${escapeJs(key)}')`,
            type: 'mustache-literal',
            key,
        });
        texts.push({ key, raw: literal.match });
    }
}

function scanTemplateBody(source, masked, range) {
    const edits = [];
    const texts = [];
    const manual = [];
    const pending = [];
    const body = masked.slice(range.startTagEnd, range.end);
    const base = range.startTagEnd;
    const stack = [];
    let i = 0;

    const pushText = (start, end) => {
        const raw = source.slice(base + start, base + end);
        const text = decodeEntities(raw);
        const trimmed = text.trim();

        // Cari blok teks terdekat (lewati <template>).
        let block = null;
        for (let s = stack.length - 1; s >= 0; s -= 1) {
            const entry = stack[s];
            if (entry.tag === 'template') continue;
            if (BLOCK_TAGS.has(entry.tag) || entry.isComponent) {
                block = entry;
                break;
            }
        }
        if (!block) return;
        if (trimmed !== '') {
            block.texts.push({ start, end, text });
            if (isTranslatableText(text)) block.hasMeaningfulText = true;
        }
        if (!isTranslatableText(text) || isTechnicalWord(trimmed)) return;

        pending.push({ start, end, text, raw, block });
    };

    while (i < body.length) {
        const char = body[i];

        if (char === '<' && body.startsWith('<!--', i)) {
            const close = body.indexOf('-->', i + 4);
            i = close === -1 ? body.length : close + 3;
            continue;
        }

        if (char === '<') {
            // cari akhir tag dengan menghormati tanda kutip
            let j = i + 1;
            let quote = null;
            while (j < body.length) {
                const c = body[j];
                if (quote) {
                    if (c === quote) quote = null;
                } else if (c === '"' || c === "'") {
                    quote = c;
                } else if (c === '>') {
                    break;
                }
                j += 1;
            }
            const tagRaw = body.slice(i + 1, j);
            const isClose = /^\s*\//.test(tagRaw);
            const isSelfClose = /\/\s*$/.test(tagRaw);
            const nameMatch = tagRaw.match(/^\s*\/?\s*([a-zA-Z][\w:.-]*)/);
            const rawName = nameMatch ? nameMatch[1] : '';
            const tagName = rawName.toLowerCase();
            const isComponent = /^[A-Z]/.test(rawName);

            if (isClose) {
                // pop sampai tag yang cocok
                const idx = stack.map((entry) => entry.tag).lastIndexOf(tagName);
                if (idx !== -1) {
                    // tandai induk bila anak memiliki teks/interpolasi bermakna
                    for (let s = stack.length - 1; s >= idx; s -= 1) {
                        const child = stack[s];
                        const parent = stack[s - 1];
                        if (parent && (child.hasMeaningfulText || child.interpolations > 0 || child.childHasText)) {
                            parent.childHasText = true;
                        }
                    }
                    stack.length = idx;
                }
            } else if (!isSelfClose && !(rawName === tagName && VOID_TAGS.has(tagName))) {
                const parent = stack[stack.length - 1];
                if (parent) {
                    parent.childElements += 1;
                    if (!/icon/i.test(tagName)) parent.nonIconChildElements += 1;
                }
                stack.push({ tag: tagName, isComponent, texts: [], interpolations: 0, childElements: 0, nonIconChildElements: 0, hasValueAttr: false, hasMeaningfulText: false, childHasText: false });
            }

            const skipContent = stack.some((entry) => SKIP_CONTENT_TAGS.has(entry.tag));
            if (!isClose && !skipContent) {
                // atribut statis
                const withoutName = tagRaw.replace(/^\s*[a-zA-Z][\w:.-]*/, '');
                const nameOffset = i + 1 + (tagRaw.length - withoutName.length);
                const re = /([^\s"'<>/=]+)(?:\s*=\s*("([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;
                let m;
                while ((m = re.exec(withoutName)) !== null) {
                    const attrName = m[1];
                    const attrValue = m[3] ?? m[4];
                    if (attrValue === undefined) {
                        if (attrName === 'value' || attrName === ':value' || attrName === 'v-bind:value') {
                            const owner = stack[stack.length - 1];
                            if (owner) owner.hasValueAttr = true;
                        }
                        continue;
                    }
                    if (attrName === 'value' || attrName === ':value' || attrName === 'v-bind:value') {
                        const owner = stack[stack.length - 1];
                        if (owner) owner.hasValueAttr = true;
                    }
                    if (attrName.startsWith(':') || attrName.startsWith('@') || attrName.startsWith('v-') || attrName.startsWith('#')) continue;
                    if (!TEXT_ATTRS.has(attrName.toLowerCase())) continue;
                    if (/\{\{|\$|=>/.test(attrValue)) continue;
                    if (!isTranslatableText(attrValue)) continue;
                    if (isTechnicalWord(attrValue.trim())) continue;
                    const key = decodeEntities(attrValue).trim().replace(/\s+/g, ' ');
                    // ganti "attr=\"nilai\"" menjadi ":attr=\"t('nilai')\""
                    const fullStart = base + nameOffset + m.index;
                    const fullEnd = base + nameOffset + m.index + m[0].length;
                    edits.push({
                        start: fullStart,
                        end: fullEnd,
                        replacement: `:${attrName}="t('${escapeJs(key)}')"`,
                        type: 'attr',
                        key,
                    });
                }
            }

            i = j + 1;
            continue;
        }

        if (body.startsWith('{{', i)) {
            const close = body.indexOf('}}', i + 2);
            for (let s = stack.length - 1; s >= 0; s -= 1) {
                if (BLOCK_TAGS.has(stack[s].tag) || stack[s].isComponent) {
                    stack[s].interpolations += 1;
                    break;
                }
            }
            const skipContent = stack.some((entry) => SKIP_CONTENT_TAGS.has(entry.tag));
            if (close !== -1 && !skipContent) {
                collectMustacheLiteralEdits(body.slice(i + 2, close), base + i + 2, edits, texts);
            }
            i = close === -1 ? body.length : close + 2;
            continue;
        }

        // teks
        let j = i;
        while (j < body.length && body[j] !== '<' && !body.startsWith('{{', j)) j += 1;
        const skipContent = stack.some((entry) => SKIP_CONTENT_TAGS.has(entry.tag));
        if (!skipContent) pushText(i, j);
        i = j;
    }

    for (const item of pending) {
        const { block, text } = item;
        // <option> tanpa atribut value: teks = nilai yang dikirim ke server.
        if (block.tag === 'option' && !block.hasValueAttr) {
            manual.push({ key: text.trim().replace(/\s+/g, ' '), reason: 'option-value' });
            continue;
        }
        const isOnlyText = block.texts.length === 1 && block.texts[0].start === item.start;
        if (!isOnlyText) {
            manual.push({ key: text.trim().replace(/\s+/g, ' '), reason: 'fragmented' });
            continue;
        }
        if (block.interpolations > 0) {
            const isLabelWithValue = block.texts.length === 1 && /[:：]\s*$/.test(text);
            if (!isLabelWithValue) {
                manual.push({ key: text.trim().replace(/\s+/g, ' '), reason: 'interpolated' });
                continue;
            }
        }
        if (block.childHasText) {
            manual.push({ key: text.trim().replace(/\s+/g, ' '), reason: 'mixed' });
            continue;
        }

        const lead = item.raw.match(/^\s*/)[0].length;
        const trail = item.raw.match(/\s*$/)[0].length;
        const key = text.trim().replace(/\s+/g, ' ');
        texts.push({ key, raw: item.raw });
        edits.push({
            start: base + item.start + lead,
            end: base + item.start + item.raw.length - trail,
            replacement: `{{ t('${escapeJs(key)}') }}`,
            type: 'text',
            key,
        });
    }

    return { edits, texts, manual };
}

/* ------------------------------------------------------------------ *
 * Script: properti objek yang berisi label UI
 * ------------------------------------------------------------------ */

const SCRIPT_PROP_NAMES = [
    'label', 'title', 'placeholder', 'description', 'hint', 'tooltip', 'text',
    'emptyText', 'emptyMessage', 'noDataText', 'searchPlaceholder', 'message',
    'subtitle', 'caption', 'header', 'confirmText', 'cancelText', 'helper',
    'confirmLabel', 'cancelLabel', 'desc', 'eyebrow', 'sub', 'summary',
    'successMessage', 'errorMessage', 'warningMessage', 'infoMessage',
    'note', 'loadingText', 'submitLabel', 'emptyLabel',
];

/**
 * Cari string literal statis pada properti UI di dalam <script setup>.
 * Hanya literal dengan kutip tunggal/ganda, bukan template literal, dan tidak
 * mengandung interpolasi.
 */
function scanScript(source) {
    const edits = [];
    const keys = [];
    const match = source.match(SCRIPT_RE);
    if (!match) return { edits, keys };

    for (const block of match) {
        if (!/lang\s*=\s*["']ts["']/.test(block) && !/setup/.test(block)) continue;
        const offset = source.indexOf(block);
        const body = block.replace(/^<script\b[^>]*>/, (m) => ' '.repeat(m.length)).replace(/<\/script\s*>$/, (m) => ' '.repeat(m.length));
        const propRe = new RegExp(`\\b(${SCRIPT_PROP_NAMES.join('|')})\\s*:\\s*(['"])((?:[^'"\\\\\\n]|\\\\.)*)\\2`, 'g');
        let m;
        while ((m = propRe.exec(body)) !== null) {
            const raw = m[3];
            if (raw.includes('${')) continue;
            const value = unescapeJs(raw);
            if (!isTranslatableText(value)) continue;
            if (isTechnicalWord(value.trim())) continue;
            const key = value.trim().replace(/\s+/g, ' ');
            const start = offset + m.index;
            const end = start + m[0].length;
            const localStart = m.index;
            const propName = m[1];
            const quote = m[2];
            const valueStartInMatch = m[0].indexOf(quote + raw + quote);
            edits.push({
                start,
                end,
                replacement: `${propName}: t('${escapeJs(key)}')`,
                type: 'script-prop',
                key,
                valueStart: offset + localStart + valueStartInMatch,
            });
            keys.push(key);
        }
    }
    return { edits, keys };
}

/* ------------------------------------------------------------------ *
 * Process
 * ------------------------------------------------------------------ */

function walk(dir, out = []) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) walk(full, out);
        else if (entry.isFile() && full.endsWith('.vue')) out.push(full);
    }
    return out;
}

function relativeImport(fromFile) {
    let rel = path.relative(path.dirname(fromFile), path.join(JS_DIR, 'i18n')).replace(/\\/g, '/');
    if (!rel.startsWith('.')) rel = `./${rel}`;
    return rel;
}

function ensureI18nImport(source, file) {
    if (/from\s+['"][^'"]*i18n['"]/.test(source)) {
        // sudah ada import; pastikan `t` tersedia
        if (/\bconst\s*\{[^}]*\bt\b[^}]*\}\s*=\s*useI18n\(\)/.test(source)) return source;
        const destructured = source.match(/\bconst\s*\{([^}]*)\}\s*=\s*useI18n\(\)/);
        if (destructured) {
            return source.replace(destructured[0], destructured[0].replace('{', '{ t,').replace('{ t, ', '{ t, '));
        }
        return source.replace(/(from\s+['"][^'"]*i18n['"];?\s*\n)/, `$1const { t } = useI18n();\n`);
    }

    const importLine = `import { useI18n } from '${relativeImport(file)}';\n`;
    const setupMatch = source.match(/<script setup[^>]*>\s*\n/);
    if (setupMatch) {
        const at = setupMatch.index + setupMatch[0].length;
        return `${source.slice(0, at)}${importLine}const { t } = useI18n();\n${source.slice(at)}`;
    }

    const scriptMatch = source.match(/<script[^>]*>\s*\n/);
    if (scriptMatch) {
        const at = scriptMatch.index + scriptMatch[0].length;
        return `${source.slice(0, at)}${importLine}const { t } = useI18n();\n${source.slice(at)}`;
    }

    // Tidak ada script: tambahkan blok script setup baru sebelum template.
    return source.replace('<template>', `<script setup>\n${importLine}const { t } = useI18n();\n</script>\n\n<template>`);
}

function applyEdits(source, edits) {
    const sorted = [...edits].sort((a, b) => b.start - a.start);
    let out = source;
    let lastStart = Infinity;
    for (const edit of sorted) {
        if (edit.end > lastStart) continue; // hindari tumpang tindih
        out = out.slice(0, edit.start) + edit.replacement + out.slice(edit.end);
        lastStart = edit.start;
    }
    return out;
}

const dictionary = loadDictionaryKeys();
const files = walk(JS_DIR);
const selected = files.filter((file) => {
    if (!ONLY) return true;
    const rel = path.relative(JS_DIR, file).replace(/\\/g, '/');
    return ONLY.some((prefix) => rel.startsWith(prefix));
});

const report = {
    generatedAt: new Date().toISOString(),
    dictionaryKeys: dictionary.size,
    scannedFiles: selected.length,
    filesWithFindings: 0,
    textNodes: 0,
    attributes: 0,
    scriptProps: 0,
    uniqueKeys: 0,
    missingKeys: [],
    byFile: [],
};

const uniqueKeys = new Set();
const keyCounts = new Map();
const missingKeys = new Set();
const manualCandidates = [];
const changedFiles = [];

function recordKey(key) {
    uniqueKeys.add(key);
    keyCounts.set(key, (keyCounts.get(key) || 0) + 1);
}

for (const file of selected) {
    const source = fs.readFileSync(file, 'utf8');
    const masked = maskBlocks(source);
    const range = findRootTemplate(masked);
    const rel = path.relative(ROOT, file).replace(/\\/g, '/');
    const entry = { file: rel, text: 0, attr: 0, script: 0, missing: 0 };
    const edits = [];

    if (range) {
        const { edits: textEdits, texts, manual } = scanTemplateBody(source, masked, range);
        edits.push(...textEdits);
        entry.text = texts.length;
        entry.attr = textEdits.filter((e) => e.type === 'attr').length;
        for (const t of texts) {
            recordKey(t.key);
        }
        for (const e of textEdits.filter((x) => x.type === 'attr')) {
            recordKey(e.key);
        }
        for (const item of manual) {
            manualCandidates.push({ file: rel, reason: item.reason, text: item.key });
        }
    }

    const scriptScan = scanScript(source);
    entry.script = scriptScan.keys.length;
    for (const key of scriptScan.keys) {
        recordKey(key);
    }
    edits.push(...scriptScan.edits);

    if (edits.length > 0) {
        report.filesWithFindings += 1;
        report.textNodes += entry.text;
        report.attributes += entry.attr;
        report.scriptProps += entry.script;
        report.byFile.push(entry);

        if (WRITE) {
            const next = ensureI18nImport(applyEdits(source, edits), file);
            if (next !== source) {
                fs.writeFileSync(file, next, 'utf8');
                changedFiles.push(rel);
            }
        }
    }
}

report.uniqueKeys = uniqueKeys.size;
report.missingKeys = [...uniqueKeys].filter((key) => !dictionary.has(key)).sort((a, b) => a.localeCompare(b));
report.missingKeysByFrequency = [...uniqueKeys]
    .filter((key) => !dictionary.has(key))
    .sort((a, b) => (keyCounts.get(b) || 0) - (keyCounts.get(a) || 0) || a.localeCompare(b))
    .map((key) => ({ key, count: keyCounts.get(key) || 0 }));
report.manualCandidates = manualCandidates;
report.byFile.sort((a, b) => b.text + b.attr + b.script - (a.text + a.attr + a.script));
report.changedFiles = changedFiles;

if (JSON_OUT) {
    fs.writeFileSync(JSON_OUT, JSON.stringify(report, null, 2), 'utf8');
}

console.log('');
console.log('=== i18n audit ===');
console.log(`File dipindai          : ${report.scannedFiles}`);
console.log(`File ada temuan        : ${report.filesWithFindings}`);
console.log(`Teks statis            : ${report.textNodes}`);
console.log(`Atribut statis         : ${report.attributes}`);
console.log(`Properti script        : ${report.scriptProps}`);
console.log(`Key unik               : ${report.uniqueKeys}`);
console.log(`Key belum ada di kamus : ${report.missingKeys.length}`);
console.log(`File diubah (--write)  : ${report.changedFiles.length}`);
console.log('');

const top = report.byFile.slice(0, 25);
if (top.length) {
    console.log('Temuan terbanyak per file:');
    for (const row of top) {
        console.log(`  ${String(row.text + row.attr + row.script).padStart(4)}  ${row.file}  (teks ${row.text}, attr ${row.attr}, script ${row.script})`);
    }
    console.log('');
}
if (JSON_OUT) console.log(`Detail JSON: ${JSON_OUT}`);
