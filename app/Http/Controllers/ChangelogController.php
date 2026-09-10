<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class ChangelogController
{
    /**
     * Category metadata for badge styles and labels.
     */
    private const CATEGORIES = [
        'Added' => [
            'label' => 'Fitur Baru',
            'tone' => 'success',
            'icon' => 'add_circle',
        ],
        'Changed' => [
            'label' => 'Pembaruan & Peningkatan',
            'tone' => 'info',
            'icon' => 'published_with_changes',
        ],
        'Fixed' => [
            'label' => 'Perbaikan Bug',
            'tone' => 'warning',
            'icon' => 'bug_report',
        ],
        'Security' => [
            'label' => 'Keamanan',
            'tone' => 'danger',
            'icon' => 'security',
        ],
        'Deprecated' => [
            'label' => 'Usang (Deprecated)',
            'tone' => 'warning',
            'icon' => 'warning',
        ],
        'Removed' => [
            'label' => 'Dihapus',
            'tone' => 'danger',
            'icon' => 'delete',
        ],
    ];

    public function index(Request $request): Response
    {
        $releases = $this->getReleases();
        $user = $request->user();
        $unitName = null;

        if ($user !== null) {
            try {
                if ($user->relationLoaded('tenant') && $user->tenant !== null) {
                    $unitName = $user->tenant->name;
                } elseif ($user->getAttribute('tenant_id') !== null && $user->tenant !== null) {
                    $unitName = $user->tenant->name;
                }
            } catch (Throwable) {
                $unitName = null;
            }
        }

        return Inertia::render('Changelog/Index', [
            'releases' => $releases,
            'latest_version' => $releases[0]['version'] ?? 'Latest',
            'total_releases' => count($releases),
            'unitName' => $unitName,
        ]);
    }

    /**
     * Get releases from cache or parse if file modified.
     *
     * @return array<int, array{
     *     version: string,
     *     date_formatted: string,
     *     is_latest: bool,
     *     total_changes: int,
     *     html: string,
     *     raw_text: string,
     *     sections: array<int, array{
     *         category: string,
     *         label: string,
     *         tone: string,
     *         icon: string,
     *         html: string,
     *         items: array<int, array{
     *             title: string,
     *             title_html: string,
     *             sub_items: array<int, array{raw: string, html: string}>,
     *             html: string
     *         }>
     *     }>
     * }>
     */
    private function getReleases(): array
    {
        $changelogPath = base_path('CHANGELOG.md');
        if (! file_exists($changelogPath)) {
            return [];
        }

        $lastModified = (int) filemtime($changelogPath);
        $cacheKey = 'changelog_parsed_'.$lastModified;

        return Cache::remember($cacheKey, 86400, fn (): array => $this->parseChangelog($changelogPath));
    }

    /**
     * @return array<int, array{
     *     version: string,
     *     date_formatted: string,
     *     is_latest: bool,
     *     total_changes: int,
     *     html: string,
     *     raw_text: string,
     *     sections: array<int, array{
     *         category: string,
     *         label: string,
     *         tone: string,
     *         icon: string,
     *         html: string,
     *         items: array<int, array{
     *             title: string,
     *             title_html: string,
     *             sub_items: array<int, array{raw: string, html: string}>,
     *             html: string
     *         }>
     *     }>
     * }>
     */
    private function parseChangelog(string $changelogPath): array
    {
        $content = (string) file_get_contents($changelogPath);
        if (trim($content) === '') {
            return [];
        }

        $pattern = '/^##\s+\[(.*?)\]/m';
        if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $releases = [];
        $total = count($matches[0]);

        for ($i = 0; $i < $total; $i++) {
            $version = $matches[1][$i][0];
            $startOffset = $matches[0][$i][1];
            $endOffset = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($content);

            $releaseBlock = substr($content, $startOffset, $endOffset - $startOffset);

            // Remove the '## [version]' header line from body
            $body = (string) preg_replace('/^##\s+\[.*?\]\r?\n+/', '', $releaseBlock);
            $body = trim($body);

            // Format date if matches YYYY-MM-DD
            $formattedDate = $version;
            try {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $version)) {
                    $carbonDate = CarbonImmutable::createFromFormat('Y-m-d', $version);
                    if ($carbonDate !== false) {
                        $months = [
                            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
                        ];
                        $formattedDate = sprintf('%d %s %d', $carbonDate->day, $months[$carbonDate->month], $carbonDate->year);
                    }
                }
            } catch (Throwable) {
                $formattedDate = $version;
            }

            // Parse sub-sections (### Added, ### Changed, etc.)
            $sections = $this->parseSections($body);

            $renderedHtml = (string) Str::markdown($body);

            $totalChanges = 0;
            foreach ($sections as $sec) {
                $totalChanges += count($sec['items']);
            }

            $releases[] = [
                'version' => $version,
                'date_formatted' => $formattedDate,
                'is_latest' => $i === 0,
                'total_changes' => $totalChanges > 0 ? $totalChanges : 1,
                'html' => $renderedHtml,
                'raw_text' => strip_tags($renderedHtml),
                'sections' => $sections,
            ];
        }

        return $releases;
    }

    /**
     * @return array<int, array{
     *     category: string,
     *     label: string,
     *     tone: string,
     *     icon: string,
     *     html: string,
     *     items: array<int, array{
     *         title: string,
     *         title_html: string,
     *         sub_items: array<int, array{raw: string, html: string}>,
     *         html: string
     *     }>
     * }>
     */
    private function parseSections(string $body): array
    {
        $pattern = '/^###\s+(.*?)$/m';
        if (! preg_match_all($pattern, $body, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $sections = [];
        $total = count($matches[0]);

        for ($i = 0; $i < $total; $i++) {
            $category = trim($matches[1][$i][0]);
            $startOffset = $matches[0][$i][1];
            $endOffset = isset($matches[0][$i + 1]) ? $matches[0][$i + 1][1] : strlen($body);

            $sectionBlock = substr($body, $startOffset, $endOffset - $startOffset);
            $sectionBody = (string) preg_replace('/^###\s+.*?\r?\n+/', '', $sectionBlock);
            $sectionBody = trim($sectionBody);

            $meta = self::CATEGORIES[$category] ?? [
                'label' => $category,
                'tone' => 'primary',
                'icon' => 'info',
            ];

            $items = $this->parseItems($sectionBody);

            $sections[] = [
                'category' => $category,
                'label' => $meta['label'],
                'tone' => $meta['tone'],
                'icon' => $meta['icon'],
                'html' => (string) Str::markdown($sectionBody),
                'items' => $items,
            ];
        }

        return $sections;
    }

    /**
     * @return array<int, array{
     *     title: string,
     *     title_html: string,
     *     sub_items: array<int, array{raw: string, html: string}>,
     *     html: string
     * }>
     */
    private function parseItems(string $sectionBody): array
    {
        $lines = explode("\n", str_replace("\r", '', $sectionBody));
        $items = [];
        $currentItem = null;

        foreach ($lines as $line) {
            if (preg_match('/^-\s+(.*)$/', $line, $matches) && ! str_starts_with($line, '  -') && ! str_starts_with($line, "\t-")) {
                if ($currentItem !== null) {
                    $currentItem['html'] = (string) Str::markdown(implode("\n", $currentItem['raw_lines']));
                    $items[] = $currentItem;
                }

                $rawLine = $matches[1];
                $title = $rawLine;

                // Extract bold title if formatted like **Title:** or **Title**
                if (preg_match('/^\*\*(.*?)\*\*[:\s]*(.*)$/', $rawLine, $titleMatch)) {
                    $title = trim($titleMatch[1]);
                    if (trim($titleMatch[2]) !== '') {
                        $title .= ' — '.trim($titleMatch[2]);
                    }
                }

                // Strip trailing colon
                $title = rtrim($title, ':');

                $currentItem = [
                    'title' => $title,
                    'title_html' => (string) Str::markdown($title),
                    'sub_items' => [],
                    'raw_lines' => [$line],
                ];
            } elseif ($currentItem !== null) {
                $currentItem['raw_lines'][] = $line;
                if (preg_match('/^\s+-\s+(.*)$/', $line, $subMatch)) {
                    $subText = trim($subMatch[1]);
                    $currentItem['sub_items'][] = [
                        'raw' => $subText,
                        'html' => (string) Str::markdown($subText),
                    ];
                }
            }
        }

        if ($currentItem !== null) {
            $currentItem['html'] = (string) Str::markdown(implode("\n", $currentItem['raw_lines']));
            $items[] = $currentItem;
        }

        return $items;
    }
}
