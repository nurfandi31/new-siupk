<?php

declare(strict_types=1);

namespace App\Domain\Documents\Services;

use App\Services\TenantSettingService;

final class SignatureTemplateService
{
    public const SETTING_KEY = 'signatures.templates';

    /** @var array<string, string> */
    public const REPORT_TYPES = [
        'default' => 'Umum (default)',
        'laporan_keuangan' => 'Laporan Keuangan',
        'rekap_pinjaman' => 'Rekap Pinjaman',
        'perjanjian_kredit' => 'Perjanjian / SPK',
        'proposal' => 'Proposal',
        'kwitansi' => 'Kwitansi',
    ];

    public function __construct(
        private TenantSettingService $settings,
    ) {}

    /**
     * @return list<array{key: string, label: string}>
     */
    public function reportTypes(): array
    {
        $out = [];
        foreach (self::REPORT_TYPES as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $stored = $this->settings->get(self::SETTING_KEY, []);
        if (! is_array($stored)) {
            return [];
        }

        $out = [];
        foreach (array_keys(self::REPORT_TYPES) as $key) {
            $html = $stored[$key] ?? '';
            $out[$key] = is_string($html) ? $html : '';
        }

        return $out;
    }

    public function get(string $reportKey): string
    {
        $all = $this->all();
        $html = $all[$reportKey] ?? '';
        if ($html !== '') {
            return $html;
        }

        if ($reportKey !== 'default') {
            return $all['default'] ?? '';
        }

        return '';
    }

    /**
     * @param  array<string, string|null>  $templates
     */
    public function save(array $templates): void
    {
        $clean = [];
        foreach (array_keys(self::REPORT_TYPES) as $key) {
            $raw = $templates[$key] ?? '';
            $clean[$key] = is_string($raw) ? $this->sanitize($raw) : '';
        }

        $this->settings->set(self::SETTING_KEY, $clean, 'json');
    }

    public function sanitize(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><table><thead><tbody><tr><th><td><div><span>';
        $stripped = strip_tags($html, $allowed);

        // Drop event handlers / javascript: URLs from remaining attributes.
        $stripped = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s+(href|src)\s*=\s*("\s*javascript:[^"]*"|\'\s*javascript:[^\']*\'|javascript:[^\s>]+)/i', '', $stripped) ?? $stripped;

        if (strlen($stripped) > 50_000) {
            $stripped = substr($stripped, 0, 50_000);
        }

        return trim($stripped);
    }

    public static function starterHtml(): string
    {
        return <<<'HTML'
<table style="width:100%">
  <tbody>
    <tr>
      <td style="width:33%;text-align:center"><p>Mengetahui,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td>
      <td style="width:33%;text-align:center"><p>Dibuat oleh,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td>
      <td style="width:33%;text-align:center"><p>Bendahara,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td>
    </tr>
  </tbody>
</table>
HTML;
    }
}
