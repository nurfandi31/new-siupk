<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ErrorPagesTest extends TestCase
{
    public function test_all_custom_error_views_render_successfully(): void
    {
        $expectedTitles = [
            400 => 'Permintaan Tidak Valid',
            401 => 'Sesi Belum Terautentikasi',
            402 => 'Langganan Tenant Diperlukan',
            403 => 'Akses Dibatasi',
            404 => 'Halaman Tidak Ditemukan',
            419 => 'Sesi Formulir Kedaluwarsa',
            429 => 'Terlalu Banyak Permintaan',
            500 => 'Terjadi Kesalahan Server',
            503 => 'Sistem Dalam Pemeliharaan',
        ];

        foreach ($expectedTitles as $code => $title) {
            $view = view("errors.{$code}", [
                'exception' => new HttpException($code, "Test custom error message for {$code}"),
            ]);

            $rendered = $view->render();

            $this->assertNotEmpty($rendered);
            $this->assertStringContainsString('siupk', $rendered);
            $this->assertStringContainsString((string) $code, $rendered);
            $this->assertStringContainsString($title, $rendered);
            $this->assertStringContainsString("Test custom error message for {$code}", $rendered);
        }
    }

    public function test_generic_layout_renders_with_defaults(): void
    {
        $rendered = view('errors.layout')->render();

        $this->assertNotEmpty($rendered);
        $this->assertStringContainsString('siupk Next', $rendered);
        $this->assertStringContainsString('BUMDesma LKD Financial System', $rendered);
    }

    public function test_non_existent_route_returns_404_view(): void
    {
        $response = $this->get('/a-route-that-does-not-exist-at-all-404-test');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Halaman Tidak Ditemukan');
    }
}
