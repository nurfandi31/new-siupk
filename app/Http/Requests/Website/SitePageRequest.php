<?php

declare(strict_types=1);

namespace App\Http\Requests\Website;

use App\Domain\Website\Models\SitePage;

final class SitePageRequest extends SiteContentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'slug' => $this->uniqueSlugRule(SitePage::class, $this->route('page')?->row_id),
        ];
    }
}
