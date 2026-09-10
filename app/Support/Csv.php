<?php

declare(strict_types=1);

namespace App\Support;

use Generator;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class Csv
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                throw new RuntimeException('Unable to open output stream.');
            }

            // Excel-friendly UTF-8 BOM.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn ($value) => $value === null ? '' : (string) $value, $row), ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: array<int, string>, 1: Generator<int, array<string, string>>}
     */
    public static function read(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('File unggahan tidak valid.');
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Tidak dapat membaca file CSV.');
        }

        $first = fgets($handle);
        if ($first === false) {
            fclose($handle);
            throw new RuntimeException('File CSV kosong.');
        }

        // Strip UTF-8 BOM if present.
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first) ?? $first;
        $delimiter = self::detectDelimiter($first);
        $headers = self::normalizeHeaders(str_getcsv($first, $delimiter));

        $rows = (function () use ($handle, $headers, $delimiter): Generator {
            $line = 1;
            while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
                $line++;
                if (self::isEmptyRow($cols)) {
                    continue;
                }

                $assoc = [];
                foreach ($headers as $index => $header) {
                    $assoc[$header] = trim((string) ($cols[$index] ?? ''));
                }
                $assoc['_line'] = (string) $line;
                yield $assoc;
            }
            fclose($handle);
        })();

        return [$headers, $rows];
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        return array_map(static function (?string $header): string {
            $value = strtolower(trim((string) $header));
            $value = str_replace([' ', '-'], '_', $value);

            return $value;
        }, $headers);
    }

    private static function detectDelimiter(string $line): string
    {
        $semicolon = substr_count($line, ';');
        $comma = substr_count($line, ',');

        return $semicolon >= $comma ? ';' : ',';
    }

    /**
     * @param  array<int, string|null>  $cols
     */
    private static function isEmptyRow(array $cols): bool
    {
        foreach ($cols as $col) {
            if (trim((string) $col) !== '') {
                return false;
            }
        }

        return true;
    }
}
