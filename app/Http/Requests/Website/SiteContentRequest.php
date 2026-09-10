<?php

declare(strict_types=1);

namespace App\Http\Requests\Website;

use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SiteContentRequest extends FormRequest
{
    use AuthorizesPermission;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'content' => ['required', 'string', 'max:200000'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
            'meta_description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'slug' => 'slug',
            'content' => 'konten',
            'status' => 'status',
            'published_at' => 'tanggal terbit',
            'meta_description' => 'deskripsi meta',
        ];
    }

    protected function uniqueSlugRule(string $model, ?int $ignoreRowId): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'nullable',
            'string',
            'max:200',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            // Pass the model class (not a raw table) so the presence check
            // resolves the tenant connection from the model, like MemberRequest.
            Rule::unique($model, 'slug')
                ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                ->ignore($ignoreRowId, 'row_id'),
        ];
    }
}
