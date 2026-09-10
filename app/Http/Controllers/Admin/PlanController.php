<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\PlanRequest;
use App\Models\Platform\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlanController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;

        $plans = Plan::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Plan $plan): array => [
                'row_id' => $plan->row_id,
                'code' => $plan->code,
                'name' => $plan->name,
                'price_amount' => $plan->price_amount,
                'currency' => $plan->currency,
                'billing_period' => $plan->billing_period,
                'is_active' => $plan->is_active,
            ]);

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
            'search' => $search,
            'perPage' => $perPage,
            'sort' => 'name',
            'direction' => 'asc',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Form', ['plan' => null]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        Plan::query()->create($request->validated());

        return to_route('admin.plans.index')->with('success', 'Plan ditambahkan.');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Admin/Plans/Form', [
            'plan' => $plan->only([
                'row_id', 'code', 'name', 'price_amount', 'currency', 'billing_period', 'is_active', 'features',
            ]),
        ]);
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->forceFill($request->validated())->save();

        return to_route('admin.plans.index')->with('success', 'Plan diperbarui.');
    }
}
