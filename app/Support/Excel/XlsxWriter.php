<?php

declare(strict_types=1);

namespace App\Support\Excel;

use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Lightweight native XLSX writer — no external dependencies.
 *
 * XLSX is a ZIP archive of XML files. This class builds the minimal required
 * XML parts to produce a valid spreadsheet that Excel, LibreOffice, and Google
 * Sheets can open.
 *
 * Supports:
 * - Multiple sheets
 * - Number format styles (Rupiah, decimal, integer)
 * - Bold / header styling
 * - Column widths
 * - Merged cells
 */
final class XlsxWriter
{
    /** @var list<array{name: string, rows: list<list<array{v: mixed, t: string, s: int}>>, cols: list<float>, merges: list<string>}> */
    private array $sheets = [];

    private int $currentSheet = -1;

    /** @var list<array{numFmt: string|null, bold: bool}> */
    private array $styles = [];

    /** @var array<string, int> */
    private array $styleIndex = [];

    /** @var list<string> */
    private array $sharedStrings = [];

    /** @var array<string, int> */
    private array $sharedStringIndex = [];

    public function __construct()
    {
        // Style 0: default (General)
        $this->registerStyle(null, false);
        // Style 1: bold
        $this->registerStyle(null, true);
        // Style 2: Rupiah (#,##0) — no decimals, thousands separator
        $this->registerStyle('#,##0', false);
        // Style 3: Rupiah bold
        $this->registerStyle('#,##0', true);
        // Style 4: 2 decimal
        $this->registerStyle('#,##0.00', false);
        // Style 5: 2 decimal bold
        $this->registerStyle('#,##0.00', true);
        // Style 6: percentage
        $this->registerStyle('0.00%', false);
        // Style 7: percentage bold
        $this->registerStyle('0.00%', true);
        // Style 8: date
        $this->registerStyle('dd/mm/yyyy', false);
    }

    public const STYLE_DEFAULT = 0;

    public const STYLE_BOLD = 1;

    public const STYLE_RUPIAH = 2;

    public const STYLE_RUPIAH_BOLD = 3;

    public const STYLE_DECIMAL = 4;

    public const STYLE_DECIMAL_BOLD = 5;

    public const STYLE_PERCENT = 6;

    public const STYLE_PERCENT_BOLD = 7;

    public const STYLE_DATE = 8;

    public function addSheet(string $name): self
    {
        $this->sheets[] = [
            'name' => $name,
            'rows' => [],
            'cols' => [],
            'merges' => [],
        ];
        $this->currentSheet = count($this->sheets) - 1;

        return $this;
    }

    /**
     * @param  list<float>  $widths
     */
    public function setColumnWidths(array $widths): self
    {
        $this->sheets[$this->currentSheet]['cols'] = $widths;

        return $this;
    }

    /**
     * @param  list<mixed>  $values
     * @param  list<int>  $styles  Style index per cell (use class constants).
     */
    public function addRow(array $values, array $styles = []): self
    {
        $row = [];
        foreach ($values as $i => $value) {
            $style = $styles[$i] ?? self::STYLE_DEFAULT;
            $row[] = $this->cell($value, $style);
        }
        $this->sheets[$this->currentSheet]['rows'][] = $row;

        return $this;
    }

    /**
     * Merge cells. $range e.g. 'A1:D1'.
     */
    public function mergeCells(string $range): self
    {
        $this->sheets[$this->currentSheet]['merges'][] = $range;

        return $this;
    }

    /**
     * Write XLSX to a temporary file and return the path.
     */
    public function save(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmp === false) {
            throw new \RuntimeException('Cannot create temp file for XLSX.');
        }
        $path = $tmp.'.xlsx';
        rename($tmp, $path);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot open ZIP for writing: {$path}");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStringsXml());

        foreach ($this->sheets as $idx => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($idx + 1).'.xml', $this->sheetXml($sheet));
        }

        $zip->close();

        return $path;
    }

    /**
     * Stream download response (Laravel).
     */
    public function download(string $filename): StreamedResponse
    {
        $path = $this->save();

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    // ─── Internal ────────────────────────────────────────────────────

    private function cell(mixed $value, int $style): array
    {
        if ($value === null || $value === '') {
            return ['v' => '', 't' => 'inlineStr', 's' => $style];
        }

        if (is_int($value) || is_float($value)) {
            return ['v' => $value, 't' => 'n', 's' => $style];
        }

        if (is_bool($value)) {
            return ['v' => $value ? 1 : 0, 't' => 'b', 's' => $style];
        }

        // String → shared string
        $str = (string) $value;
        if (! isset($this->sharedStringIndex[$str])) {
            $this->sharedStringIndex[$str] = count($this->sharedStrings);
            $this->sharedStrings[] = $str;
        }

        return ['v' => $this->sharedStringIndex[$str], 't' => 's', 's' => $style];
    }

    private function registerStyle(?string $numFmt, bool $bold): int
    {
        $key = ($numFmt ?? 'General').($bold ? ':B' : ':N');
        if (isset($this->styleIndex[$key])) {
            return $this->styleIndex[$key];
        }
        $idx = count($this->styles);
        $this->styles[] = ['numFmt' => $numFmt, 'bold' => $bold];
        $this->styleIndex[$key] = $idx;

        return $idx;
    }

    private static function colLetter(int $col): string
    {
        $letter = '';
        $col++;
        while ($col > 0) {
            $col--;
            $letter = chr(65 + ($col % 26)).$letter;
            $col = intdiv($col, 26);
        }

        return $letter;
    }

    private function esc(string $str): string
    {
        return htmlspecialchars($str, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        $sheets = '';
        foreach ($this->sheets as $idx => $_) {
            $n = $idx + 1;
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .$sheets
            .'</Types>';
    }

    private function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $idx => $sheet) {
            $n = $idx + 1;
            $sheets .= '<sheet name="'.$this->esc($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $idx => $_) {
            $n = $idx + 1;
            $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
        }
        $n = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $n++;
        $rels .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        // Collect custom number formats (id starts at 164)
        $fmtMap = []; // numFmt string => id
        $numFmtsXml = '';
        $nextId = 164;
        foreach ($this->styles as $st) {
            if ($st['numFmt'] !== null && ! isset($fmtMap[$st['numFmt']])) {
                $fmtMap[$st['numFmt']] = $nextId;
                $numFmtsXml .= '<numFmt numFmtId="'.$nextId.'" formatCode="'.$this->esc($st['numFmt']).'"/>';
                $nextId++;
            }
        }

        $fonts = '<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>';

        $fills = '<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>';

        $borders = '<border><left/><right/><top/><bottom/><diagonal/></border>';

        $xfs = '';
        foreach ($this->styles as $st) {
            $fontId = $st['bold'] ? 1 : 0;
            $numFmtId = $st['numFmt'] !== null ? $fmtMap[$st['numFmt']] : 0;
            $applyFmt = $st['numFmt'] !== null ? ' applyNumberFormat="1"' : '';
            $applyFont = $st['bold'] ? ' applyFont="1"' : '';
            $xfs .= '<xf numFmtId="'.$numFmtId.'" fontId="'.$fontId.'" fillId="0" borderId="0"'.$applyFmt.$applyFont.'/>';
        }

        $numFmtsBlock = $numFmtsXml !== '' ? '<numFmts count="'.count($fmtMap).'">'.$numFmtsXml.'</numFmts>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$numFmtsBlock
            .'<fonts count="2">'.$fonts.'</fonts>'
            .'<fills count="2">'.$fills.'</fills>'
            .'<borders count="1">'.$borders.'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($this->styles).'">'.$xfs.'</cellXfs>'
            .'</styleSheet>';
    }

    private function sharedStringsXml(): string
    {
        $count = count($this->sharedStrings);
        $items = '';
        foreach ($this->sharedStrings as $str) {
            $items .= '<si><t>'.$this->esc($str).'</t></si>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$count.'" uniqueCount="'.$count.'">'
            .$items
            .'</sst>';
    }

    /**
     * @param  array{name: string, rows: list<list<array{v: mixed, t: string, s: int}>>, cols: list<float>, merges: list<string>}  $sheet
     */
    private function sheetXml(array $sheet): string
    {
        $colsXml = '';
        if (! empty($sheet['cols'])) {
            $colsXml = '<cols>';
            foreach ($sheet['cols'] as $i => $w) {
                $n = $i + 1;
                $colsXml .= '<col min="'.$n.'" max="'.$n.'" width="'.$w.'" customWidth="1"/>';
            }
            $colsXml .= '</cols>';
        }

        $rowsXml = '';
        foreach ($sheet['rows'] as $rowIdx => $cells) {
            $r = $rowIdx + 1;
            $cellsXml = '';
            foreach ($cells as $colIdx => $cell) {
                $ref = self::colLetter($colIdx).$r;
                $s = $cell['s'];
                $t = $cell['t'];
                $v = $cell['v'];

                if ($v === '' && $t === 'inlineStr') {
                    $cellsXml .= '<c r="'.$ref.'" s="'.$s.'"/>';
                } elseif ($t === 'inlineStr') {
                    $cellsXml .= '<c r="'.$ref.'" s="'.$s.'" t="inlineStr"><is><t>'.$this->esc((string) $v).'</t></is></c>';
                } else {
                    $cellsXml .= '<c r="'.$ref.'" s="'.$s.'" t="'.$t.'"><v>'.$v.'</v></c>';
                }
            }
            $rowsXml .= '<row r="'.$r.'">'.$cellsXml.'</row>';
        }

        $mergesXml = '';
        if (! empty($sheet['merges'])) {
            $mergesXml = '<mergeCells count="'.count($sheet['merges']).'">';
            foreach ($sheet['merges'] as $m) {
                $mergesXml .= '<mergeCell ref="'.$m.'"/>';
            }
            $mergesXml .= '</mergeCells>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$colsXml
            .'<sheetData>'.$rowsXml.'</sheetData>'
            .$mergesXml
            .'</worksheet>';
    }
}
