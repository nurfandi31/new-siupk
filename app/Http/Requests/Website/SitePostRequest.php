<?php

declare(strict_types=1);

namespace App\Http\Requests\Website;

use App\Domain\Website\Models\SitePost;

final class SitePostRequest extends SiteContentRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'slug' => $this->uniqueSlugRule(SitePost::class, $this->route('post')?->row_id),
            'excerpt' => ['nullable', 'string', 'max:500'],
            'cover_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'excerpt' => 'ringkasan',
            'cover_image' => 'gambar sampul',
        ];
    }
}
