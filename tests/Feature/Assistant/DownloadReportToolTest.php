<?php

declare(strict_types=1);

namespace Tests\Feature\Assistant;

use App\Assistant\Handlers\DownloadReportHandler;
use App\Domain\Assistant\Services\AssistantToolService;
use Enpii\Assistant\Services\Tools\ToolRegistry;
use Tests\TestCase;

final class DownloadReportToolTest extends TestCase
{
    public function test_download_report_handler_metadata(): void
    {
        /** @var DownloadReportHandler $handler */
        $handler = app(DownloadReportHandler::class);

        self::assertSame('download_report', $handler->name());
        self::assertFalse($handler->requiresConfirmation());
        self::assertStringContainsString('direct download', $handler->description());

        $schema = $handler->jsonSchema();
        self::assertSame('object', $schema['type']);
        self::assertContains('report_type', $schema['required']);
    }

    public function test_download_report_registered_in_tool_registry(): void
    {
        /** @var ToolRegistry $registry */
        $registry = app(ToolRegistry::class);

        $tool = $registry->resolve('download_report');
        self::assertNotNull($tool);
        self::assertSame('download_report', $tool->name());
    }

    public function test_download_balance_sheet_pdf_forces_direct_download(): void
    {
        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->downloadReport([
            'report_type' => 'balance_sheet',
            'format' => 'pdf',
            'month' => 8,
            'year' => 2026,
        ]);

        self::assertTrue($res['ok']);
        self::assertSame('balance_sheet', $res['report_type']);
        self::assertSame('Neraca', $res['short_name']);
        self::assertSame('pdf', $res['format']);
        self::assertSame('Agustus 2026', $res['period']);
        self::assertSame('/accounting/reports/balance-sheet/pdf?month=8&year=2026&download=1', $res['download_url']);
        self::assertStringContainsString('::button{', $res['action_button']);
        self::assertStringContainsString('Unduh Neraca (PDF)', $res['action_button']);
        self::assertStringContainsString('download=1', $res['action_button']);
        self::assertStringContainsString('download', $res['action_button']);
        parse_str(parse_url($res['download_url'], PHP_URL_QUERY) ?? '', $query);
        self::assertSame('8', $query['month']);
        self::assertSame('2026', $query['year']);
    }

    public function test_download_income_statement_excel(): void
    {
        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->downloadReport([
            'report_type' => 'laba_rugi',
            'format' => 'excel',
            'month' => 7,
            'year' => 2026,
        ]);

        self::assertTrue($res['ok']);
        self::assertSame('income_statement', $res['report_type']);
        self::assertSame('Laba Rugi', $res['short_name']);
        self::assertSame('excel', $res['format']);
        self::assertSame('/accounting/reports/income-statement/excel?month=7&year=2026', $res['download_url']);
        self::assertStringContainsString('Unduh Laba Rugi (EXCEL)', $res['action_button']);
    }

    public function test_download_lending_portfolio_pdf_forces_direct_download(): void
    {
        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->downloadReport([
            'report_type' => 'portfolio',
            'format' => 'pdf',
            'year' => 2026,
        ]);

        self::assertTrue($res['ok']);
        self::assertSame('portfolio', $res['report_type']);
        self::assertSame('Portofolio', $res['short_name']);
        parse_str(parse_url($res['download_url'], PHP_URL_QUERY) ?? '', $query);
        self::assertSame('2026-12-31', $query['as_of']);
        self::assertArrayNotHasKey('month', $query);
        self::assertArrayNotHasKey('year', $query);
        self::assertStringContainsString('Unduh Portofolio (PDF)', $res['action_button']);
    }

    public function test_download_journals_pdf_uses_controller_period_contract(): void
    {
        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->downloadReport([
            'report_type' => 'journals',
            'format' => 'pdf',
            'month' => 7,
            'year' => 2026,
        ]);

        self::assertTrue($res['ok']);
        parse_str(parse_url($res['download_url'], PHP_URL_QUERY) ?? '', $query);
        self::assertSame('7', $query['month']);
        self::assertSame('2026', $query['year']);
        self::assertSame('1', $query['download']);
        self::assertArrayNotHasKey('from', $query);
        self::assertArrayNotHasKey('to', $query);
    }

    public function test_download_general_ledger_pdf_uses_controller_account_contract(): void
    {
        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->downloadReport([
            'report_type' => 'general_ledger',
            'format' => 'pdf',
            'month' => 7,
            'year' => 2026,
            'account_id' => 42,
        ]);

        self::assertTrue($res['ok']);
        parse_str(parse_url($res['download_url'], PHP_URL_QUERY) ?? '', $query);
        self::assertSame('7', $query['month']);
        self::assertSame('2026', $query['year']);
        self::assertSame('42', $query['account']);
        self::assertSame('1', $query['download']);
        self::assertArrayNotHasKey('account_id', $query);
    }

    public function test_download_lending_schedule_pdf_uses_controller_period_contract(): void
    {
        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->downloadReport([
            'report_type' => 'schedule_vs_actual',
            'format' => 'pdf',
            'month' => 7,
            'year' => 2026,
        ]);

        self::assertTrue($res['ok']);
        parse_str(parse_url($res['download_url'], PHP_URL_QUERY) ?? '', $query);
        self::assertSame('7', $query['month']);
        self::assertSame('2026', $query['year']);
        self::assertSame('1', $query['download']);
    }

    public function test_download_calk_falls_back_to_pdf_download(): void
    {
        /** @var AssistantToolService $tools */
        $tools = app(AssistantToolService::class);

        $res = $tools->downloadReport([
            'report_type' => 'calk',
            'format' => 'excel',
            'month' => 12,
            'year' => 2025,
        ]);

        self::assertTrue($res['ok']);
        self::assertSame('calk', $res['report_type']);
        self::assertSame('CALK', $res['short_name']);
        self::assertSame('pdf', $res['format']);
        self::assertSame('/accounting/reports/calk/pdf?month=12&year=2025&download=1', $res['download_url']);
        self::assertStringContainsString('Unduh CALK (PDF)', $res['action_button']);
    }
}
