<?php

declare(strict_types=1);

namespace App\Domain\Membership\Models;

use App\Models\Tenant\TenantModel;
use Carbon\CarbonImmutable;

final class OrganizationProfile extends TenantModel
{
    protected $table = 'organization_profiles';

    protected $fillable = [
        'id',
        'tenant_id',
        'legal_name',
        'short_name',
        'registration_number',
        'tax_number',
        'address',
        'district_name',
        'regency_name',
        'phone',
        'email',
        'website',
        'logo_path',
        'timezone',
        'operational_start_date',
        'installment_rounding',
        'disbursement_cutoff_day',
        'village_installment_day',
        'spk_template',
        'spk_template_individual',
        'signature_config',
        'signature_config_individual',
        'operational_start_date_v2',
        'manager_title',
        'secretary_title',
        'treasurer_title',
        'verifier_title',
        'manager_name',
        'secretary_name',
        'treasurer_name',
        'verifier_name',
        'brand_short',
        'contact_email_secondary',
        'collectibility_rules',
    ];

    protected function casts(): array
    {
        return [
            'operational_start_date' => 'immutable_date',
            'operational_start_date_v2' => 'immutable_date',
            'signature_config' => 'array',
            'signature_config_individual' => 'array',
            'collectibility_rules' => 'array',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = $this->logo_path;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    public function displayName(): string
    {
        return $this->short_name ?: $this->legal_name;
    }

    public function operationalStartDate(): ?CarbonImmutable
    {
        return $this->operational_start_date;
    }
}
