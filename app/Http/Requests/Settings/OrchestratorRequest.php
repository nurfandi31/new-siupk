<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class OrchestratorRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'orchestrator_base_url' => ['required', 'string', 'max:255', 'url'],
            'orchestrator_public_url' => ['nullable', 'string', 'max:255', 'url'],
            'adapter_base_url' => ['nullable', 'string', 'max:255', 'url'],
            'shared_secret' => ['nullable', 'string', 'min:8', 'max:255'],
            'widget_enabled' => ['required', 'boolean'],
            'signature_max_skew_ms' => ['nullable', 'integer', 'min:1000', 'max:3600000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'orchestrator_base_url' => 'URL server orchestrator',
            'orchestrator_public_url' => 'URL publik widget',
            'adapter_base_url' => 'URL callback adapter',
            'shared_secret' => 'shared secret',
            'widget_enabled' => 'widget aktif',
            'signature_max_skew_ms' => 'skew timestamp',
        ];
    }
}
