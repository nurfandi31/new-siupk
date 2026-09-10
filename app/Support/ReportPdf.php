<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportPdf
{
    /**
     * @param  array<string, mixed>  $data
     * @param  string|array{0: float, 1: float, 2: float, 3: float}  $paper  Paper size name (e.g. 'a4') or [x, y, w, h] in points.
     */
    public function stream(string $view, array $data, string $filename, string $orientation = 'portrait', string|array $paper = 'a4'): Response|StreamedResponse
    {
        $pdf = Pdf::loadView($view, $data)->setPaper($paper, $orientation);

        if (request()->boolean('download') || request()->query('download') === '1') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function download(string $view, array $data, string $filename, string $orientation = 'portrait', string|array $paper = 'a4'): Response|StreamedResponse
    {
        $pdf = Pdf::loadView($view, $data)->setPaper($paper, $orientation);

        return $pdf->download($filename);
    }
}
