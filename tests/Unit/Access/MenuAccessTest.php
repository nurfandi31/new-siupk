<?php

declare(strict_types=1);

namespace Tests\Unit\Access;

use App\Domain\Access\Services\MenuAccess;
use App\Tenancy\TenantContext;
use Tests\TestCase;

final class MenuAccessTest extends TestCase
{
    private MenuAccess $menus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menus = new MenuAccess(app(TenantContext::class));
    }

    public function test_catalog_keys_and_paths_are_unique(): void
    {
        $keys = $this->menus->keys();
        $paths = array_keys($this->menus->pathMap());

        $this->assertNotEmpty($keys);
        $this->assertSame(count($keys), count(array_unique($keys)), 'Menu keys must be unique.');
        $this->assertSame(count($keys), count(array_unique($paths)), 'Menu paths must be unique.');
    }

    public function test_exact_path_matches_its_menu(): void
    {
        $this->assertSame('journals.index', $this->menus->match('/accounting/journals'));
        $this->assertSame('journals.index', $this->menus->match('/accounting/journals?page=2'));
        $this->assertSame('members.create', $this->menus->match('/master-data/members/create'));
    }

    public function test_longest_prefix_wins(): void
    {
        $this->assertSame(
            'installments.group',
            $this->menus->match('/accounting/journal-entries/installment'),
        );
        $this->assertSame(
            'installments.individual',
            $this->menus->match('/accounting/journal-entries/installment-individual'),
        );
        $this->assertSame('simpanan.index', $this->menus->match('/simpanan/123'));
        $this->assertSame('loans.index', $this->menus->match('/lending/loans/45/edit'));
    }

    public function test_unlisted_paths_are_not_restricted(): void
    {
        $this->assertNull($this->menus->match('/dashboard'));
        $this->assertNull($this->menus->match('/profile'));
        $this->assertNull($this->menus->match('/search'));
    }
}
