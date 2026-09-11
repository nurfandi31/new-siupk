/**
 * Markdown rendering for the assistant widget.
 *
 * Two output shapes:
 *   - formatMarkdown(text) → HTML string (legacy; used inside Artifact modal)
 *   - parseMarkdownTree(text) → Block[] tree (used by AssistantWidget for
 *     interactive component rendering)
 *
 * Supports:
 *   - fenced code blocks
 *   - inline code, bold, italic
 *   - headings (h1, h2, h3)
 *   - bullet & numbered lists
 *   - tables (header + separator + body rows)
 *   - paragraphs (double newlines)
 *   - soft line breaks (single newline → <br>)
 *   - component blocks (::type{json}::body::) → artifact / button / poll
 *
 * All input is HTML-escaped first, then structural markdown is replaced.
 */

export function escapeHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

export function parseTableRow(line) {
    const trimmed = line.trim();
    if (/^\|?[\s:|-]+\|?$/.test(trimmed) && /-/.test(trimmed) && !/[A-Za-z0-9]/.test(trimmed)) {
        return null;
    }
    let inner = trimmed;
    if (inner.startsWith('|')) inner = inner.slice(1);
    if (inner.endsWith('|')) inner = inner.slice(0, -1);
    return inner.split('|').map(c => c.trim());
}

/**
 * Parse attribute JSON from `::type{json}` head. Tolerant to single quotes
 * and missing braces (returns {} in that case).
 */
function parseComponentAttrs(raw) {
    const trimmed = (raw ?? '').trim();
    if (!trimmed) return {};
    const withBraces = trimmed.startsWith('{') && trimmed.endsWith('}') ? trimmed : '{' + trimmed + '}';
    try {
        return JSON.parse(withBraces);
    } catch (_e) {
        try {
            return JSON.parse(withBraces.replace(/'/g, '"'));
        } catch (_e2) {
            return {};
        }
    }
}

/**
 * Parse the body of a `::poll` block as a list of {label, value} pairs.
 * Accepts `- Label|value`, `* Label|value`, `1. Label|value`, or bare text.
 */
function parsePollBody(body) {
    const lines = body.split('\n').map(l => l.trim()).filter(Boolean);
    const options = [];
    for (const line of lines) {
        const m = line.match(/^(?:[-*]|\d+\.)\s+(.+)$/);
        if (!m) continue;
        const rest = m[1];
        const pipeIdx = rest.indexOf('|');
        if (pipeIdx >= 0) {
            options.push({
                label: rest.slice(0, pipeIdx).trim(),
                value: rest.slice(pipeIdx + 1).trim(),
            });
        } else {
            options.push({ label: rest, value: rest });
        }
    }
    return options;
}

/**
 * Generate a short id from block content + type + index in the message.
 * Used as :key and for tracking submitted state per (msg, block).
 */
let blockCounter = 0;
export function nextBlockId() {
    blockCounter += 1;
    return `b_${Date.now().toString(36)}_${blockCounter}`;
}

/**
 * Render an inner markdown string into HTML (used for artifact body).
 * Same rules as formatMarkdown; exported separately so component bodies
 * can re-render when the artifact modal opens.
 */
export function renderMarkdownHtml(raw) {
    if (!raw) return '';
    let text = String(raw).replace(/\r\n/g, '\n');

    text = text.replace(/```([\s\S]*?)```/g, (_, code) =>
        `<pre class="md-pre"><code>${escapeHtml(code.replace(/^\n|\n$/g, ''))}</code></pre>`);

    const blocks = text.split(/(<pre class="md-pre">[\s\S]*?<\/pre>)/);
    return blocks.map((block) => {
        if (block.startsWith('<pre class="md-pre">')) return block;

        let t = block.replace(/([:;])\s+[-–—]\s+/g, '$1\n- ').replace(/\s+[-–—]\s+(?=[A-Z0-9*])/g, '\n- ');
        t = escapeHtml(t);

        t = t.replace(/`([^`]+)`/g, '<code class="md-code">$1</code>');
        t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
        t = t.replace(/^### (.+)$/gm, '<div class="md-h3">$1</div>');
        t = t.replace(/^## (.+)$/gm, '<div class="md-h2">$1</div>');
        t = t.replace(/^# (.+)$/gm, '<div class="md-h2">$1</div>');

        t = t.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-primary underline font-medium inline-flex items-center gap-0.5 hover:opacity-80">$1<span class="material-symbols-outlined text-[13px] leading-none">open_in_new</span></a>');

        t = t.replace(
            /(?:^\|?[ \t]*:?-+:?[ \t]*(\|[ \t]*:?-+:?[ \t]*)+\|?[ \t]*\n)((?:^\|?.+\n?)+)/gm,
            (match, _sep, rowsBlock) => {
                const rows = rowsBlock.trim().split('\n').map(parseTableRow).filter(r => r !== null);
                if (rows.length === 0) return match;
                const headerCells = rows.shift();
                const headerHtml = '<tr>' + headerCells.map(c => `<th>${c}</th>`).join('') + '</tr>';
                const bodyHtml = rows.map(r =>
                    '<tr>' + r.map(c => `<td>${c}</td>`).join('') + '</tr>'
                ).join('');
                return `<table class="md-table"><thead>${headerHtml}</thead><tbody>${bodyHtml}</tbody></table>`;
            }
        );

        t = t.replace(/(?:^(?:[-*]|\d+\.) .+(?:\n|$))+/gm, (block2) => {
            const items = block2.trim().split('\n').map((line) =>
                line.replace(/^(?:[-*]|\d+\.)\s+/, ''));
            return `<ul class="md-ul">${items.map((i) => `<li>${i}</li>`).join('')}</ul>`;
        });

        t = t.replace(/\n\n+/g, '</p><p class="md-p">');
        t = t.replace(/\n/g, '<br>');

        if (!t.startsWith('<ul') && !t.startsWith('<div') && !t.startsWith('<pre') && !t.startsWith('<table')) {
            t = `<p class="md-p">${t}</p>`;
        }
        return t;
    }).join('');
}

/**
 * Parse assistant message text into a renderable tree of blocks.
 *
 * Block shapes:
 *   { type: 'heading', level: 1|2|3, text: '...' }
 *   { type: 'paragraph', html: '...' }
 *   { type: 'code', html: '...' }
 *   { type: 'artifact', id, kind, title, summary, markdown }
 *   { type: 'button', id, label, value }
 *   { type: 'poll', id, question, options, allowOther }
 */
export function parseMarkdownTree(raw) {
    if (!raw) return [];
    const text = String(raw).replace(/\r\n/g, '\n');

    const tokens = [];
    let cursor = 0;

    // Pattern 1: ::type{json}::body::
    const compRe = /::(artifact|button|poll)\s*\{([\s\S]*?)\}(?:\s*::|\s*\n([\s\S]*?)\n::|\s*\n::)/g;
    // Pattern 2: fenced code
    const codeRe = /```([\s\S]*?)```/g;

    // Find all component/code spans, then walk linearly
    const spans = [];
    let m;
    while ((m = compRe.exec(text)) !== null) {
        spans.push({ start: m.index, end: m.index + m[0].length, kind: 'component', match: m });
    }
    while ((m = codeRe.exec(text)) !== null) {
        spans.push({ start: m.index, end: m.index + m[0].length, kind: 'code', match: m });
    }
    spans.sort((a, b) => a.start - b.start);

    for (const span of spans) {
        // Emit plain text before this span as a paragraph (or fragment)
        if (span.start > cursor) {
            pushMarkdownSegment(tokens, text.slice(cursor, span.start));
        }
        if (span.kind === 'component') {
            const [, type, attrsRaw, body] = span.match;
            const attrs = parseComponentAttrs(attrsRaw);
            const id = nextBlockId();
            if (type === 'artifact') {
                tokens.push({
                    type: 'artifact',
                    id,
                    kind: attrs.type ?? 'table',
                    title: attrs.title ?? 'Detail',
                    summary: attrs.summary ?? attrs.count ?? '',
                    markdown: (body ?? '').trim(),
                });
            } else if (type === 'button') {
                tokens.push({
                    type: 'button',
                    id,
                    label: attrs.label ?? attrs.title ?? attrs.value ?? 'Pilih',
                    value: attrs.value ?? attrs.label ?? '',
                    url: attrs.url ?? attrs.href ?? null,
                    icon: attrs.icon ?? (attrs.url || attrs.href ? 'open_in_new' : 'check'),
                    target: attrs.target ?? '_blank',
                });
            } else {
                tokens.push({
                    type: 'poll',
                    id,
                    question: attrs.question ?? 'Pilih salah satu',
                    options: parsePollBody(body ?? ''),
                    allowOther: attrs.allow_other === true || attrs.allow_other === 'true',
                });
            }
        } else if (span.kind === 'code') {
            const [, code] = span.match;
            tokens.push({
                type: 'code',
                html: `<pre class="md-pre"><code>${escapeHtml(code.replace(/^\n|\n$/g, ''))}</code></pre>`,
            });
        }
        cursor = span.end;
    }
    if (cursor < text.length) {
        pushMarkdownSegment(tokens, text.slice(cursor));
    }

    return tokens;
}

function pushMarkdownSegment(tokens, raw) {
    if (!raw.trim()) return;
    const paragraphs = raw.split(/\n\s*\n+/);
    for (const para of paragraphs) {
        const t = para.trim();
        if (!t) continue;
        // Detect simple single-line headings first
        const headingMatch = t.match(/^(#{1,3})\s+(.+)$/);
        if (headingMatch && !t.includes('\n')) {
            const level = headingMatch[1].length;
            tokens.push({ type: 'heading', level, text: headingMatch[2].trim() });
            continue;
        }
        tokens.push({ type: 'paragraph', html: renderMarkdownHtml(t) });
    }
}

/**
 * Backwards-compatible HTML output for any caller that still wants a string.
 * Internally renders each block via its HTML or raw markdown path.
 */
export function formatMarkdown(raw) {
    if (!raw) return '';
    const tree = parseMarkdownTree(raw);
    return tree.map((block) => {
        if (block.type === 'artifact') return renderMarkdownHtml(block.markdown);
        if (block.type === 'button' || block.type === 'poll') return ''; // components render themselves
        return block.html || '';
    }).filter(Boolean).join('\n');
}

export function useMarkdown() {
    return { formatMessage: formatMarkdown, parseMarkdownTree, renderMarkdownHtml, escapeHtml };
}