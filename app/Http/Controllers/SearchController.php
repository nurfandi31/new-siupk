<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Search\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController
{
    public function __construct(
        private readonly GlobalSearchService $search,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $q = (string) $request->query('q', '');
        $payload = $this->search->search($q, $user);

        return response()->json($payload);
    }
}
