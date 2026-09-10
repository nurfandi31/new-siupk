<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;

/**
 * Read-only bagan akun. Mutasi COA = pusat (bukan tenant UI).
 */
final class ChartOfAccountsService
{
    public const TYPES = [
        'asset' => 'Aset',
        'liability' => 'Kewajiban',
        'equity' => 'Ekuitas',
        'revenue' => 'Pendapatan',
        'expense' => 'Beban',
    ];

    /**
     * @return array{
     *   rows: list<array<string, mixed>>,
     *   filters: array{q:?string,type:?string,status:string},
     *   type_options: list<array{value:string,label:string}>,
     *   counts: array{total:int,active:int,postable:int}
     * }
     */
    public function list(?string $q = null, ?string $type = null, string $status = 'all'): array
    {
        $query = Account::query()->orderBy('code');

        if (is_string($q) && trim($q) !== '') {
            $term = '%'.trim($q).'%';
            $query->where(function ($w) use ($term): void {
                $w->where('code', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        if (is_string($type) && $type !== '' && $type !== 'all' && isset(self::TYPES[$type])) {
            $query->where('account_type', $type);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $rows = $query->get()->map(fn (Account $a): array => [
            'row_id' => (int) $a->row_id,
            'code' => (string) $a->code,
            'name' => (string) $a->name,
            'account_type' => (string) $a->account_type,
            'type_label' => self::TYPES[(string) $a->account_type] ?? (string) $a->account_type,
            'normal_balance' => (string) $a->normal_balance,
            'level' => (int) $a->level,
            'is_postable' => (bool) $a->is_postable,
            'is_active' => (bool) $a->is_active,
            'created_at' => $a->created_at?->toDateString(),
            'deactivated_at' => $a->deactivated_at?->toDateString(),
            'parent_row_id' => $a->parent_row_id !== null ? (int) $a->parent_row_id : null,
        ])->all();

        $base = Account::query();
        $counts = [
            'total' => (int) (clone $base)->count(),
            'active' => (int) (clone $base)->where('is_active', true)->count(),
            'postable' => (int) (clone $base)->where('is_postable', true)->where('is_active', true)->count(),
        ];

        $typeOptions = [
            ['value' => 'all', 'label' => 'Semua jenis'],
        ];
        foreach (self::TYPES as $value => $label) {
            $typeOptions[] = ['value' => $value, 'label' => $label];
        }

        return [
            'rows' => $rows,
            'filters' => [
                'q' => is_string($q) && trim($q) !== '' ? trim($q) : null,
                'type' => is_string($type) && $type !== '' && $type !== 'all' ? $type : null,
                'status' => in_array($status, ['all', 'active', 'inactive'], true) ? $status : 'all',
            ],
            'type_options' => $typeOptions,
            'counts' => $counts,
        ];
    }
}
