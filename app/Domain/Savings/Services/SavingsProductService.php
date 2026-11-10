<?php

declare(strict_types=1);

namespace App\Domain\Savings\Services;

use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Savings\Models\SavingsProduct;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD jenis simpanan. Penghapusan ditolak bila masih dipakai rekening.
 */
final class SavingsProductService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        return SavingsProduct::query()
            ->withCount('accounts')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->map(fn (SavingsProduct $product): array => $this->row($product))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(SavingsProduct $product): array
    {
        return [
            'row_id' => (int) $product->row_id,
            'code' => (string) $product->code,
            'name' => (string) $product->name,
            'category' => (string) $product->category,
            'category_label' => $product->categoryLabel(),
            'form_type' => (int) $product->form_type,
            'form_type_label' => $product->formTypeLabel(),
            'interest_rate' => (float) $product->interest_rate,
            'tax_rate' => (float) $product->tax_rate,
            'admin_fee' => (float) $product->admin_fee,
            'opening_fee' => (float) $product->opening_fee,
            'minimum_balance' => (float) $product->minimum_balance,
            'is_active' => (bool) $product->is_active,
            'sort_order' => (int) $product->sort_order,
            'description' => $product->description,
            'accounts_count' => (int) ($product->accounts_count ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SavingsProduct
    {
        $code = trim((string) ($data['code'] ?? ''));

        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'Kode jenis simpanan wajib diisi.']);
        }

        if (SavingsProduct::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Kode jenis simpanan sudah dipakai.']);
        }

        try {
            return SavingsProduct::query()->create($this->payload($data, $code));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Kode jenis simpanan sudah dipakai.']);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SavingsProduct $product, array $data): SavingsProduct
    {
        $code = trim((string) ($data['code'] ?? $product->code));

        if ($code !== (string) $product->code
            && SavingsAccount::query()->where('product_row_id', $product->row_id)->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Kode jenis simpanan tidak dapat diubah karena jenis ini sudah memiliki rekening.',
            ]);
        }

        $duplicate = SavingsProduct::query()
            ->where('code', $code)
            ->whereKeyNot($product->row_id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['code' => 'Kode jenis simpanan sudah dipakai.']);
        }

        try {
            $product->forceFill($this->payload($data, $code, $product))->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'Kode jenis simpanan sudah dipakai.']);
        }

        return $product->fresh() ?? $product;
    }

    public function delete(SavingsProduct $product): void
    {
        $accounts = SavingsAccount::query()->where('product_row_id', $product->row_id)->count();

        if ($accounts > 0) {
            throw new DomainException(
                "Jenis simpanan tidak dapat dihapus karena masih dipakai oleh {$accounts} rekening.",
            );
        }

        DB::connection((string) config('tenancy.tenant_connection', 'tenant'))->transaction(
            fn () => $product->delete(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, string $code, ?SavingsProduct $existing = null): array
    {
        $category = (string) ($data['category'] ?? $existing?->category ?? 'lainnya');

        if (! array_key_exists($category, SavingsProduct::CATEGORIES)) {
            $category = 'lainnya';
        }

        return [
            'code' => $code,
            'name' => trim((string) ($data['name'] ?? $existing?->name ?? '')),
            'category' => $category,
            'form_type' => in_array((int) ($data['form_type'] ?? $existing?->form_type ?? 1), [1, 2, 3], true)
                ? (int) ($data['form_type'] ?? $existing?->form_type ?? 1)
                : 1,
            'interest_rate' => $this->rate($data['interest_rate'] ?? $existing?->interest_rate ?? 0),
            'tax_rate' => $this->rate($data['tax_rate'] ?? $existing?->tax_rate ?? 0),
            'admin_fee' => $this->money($data['admin_fee'] ?? $existing?->admin_fee ?? 0),
            'opening_fee' => $this->money($data['opening_fee'] ?? $existing?->opening_fee ?? 0),
            'minimum_balance' => $this->money($data['minimum_balance'] ?? $existing?->minimum_balance ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? $existing?->is_active ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? $existing?->sort_order ?? 0),
            'description' => $data['description'] ?? $existing?->description,
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function rate(mixed $value): string
    {
        return number_format((float) $value, 3, '.', '');
    }
}
