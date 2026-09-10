<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Services\ChartOfAccountsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ChartOfAccountsController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly ChartOfAccountsService $coa,
    ) {}

    public function index(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'journals.view');

        $q = $request->query('q');
        $type = $request->query('type');
        $status = (string) $request->query('status', 'all');

        $payload = $this->coa->list(
            q: is_string($q) ? $q : null,
            type: is_string($type) ? $type : null,
            status: $status,
        );

        return Inertia::render('Accounting/ChartOfAccounts/Index', $payload);
    }
}
