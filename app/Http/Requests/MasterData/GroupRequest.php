<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Domain\Membership\Models\Member;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Models\Tenant\ActivityType;
use App\Models\Tenant\BusinessType;
use App\Models\Tenant\GroupFunction;
use App\Models\Tenant\GroupLevel;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class GroupRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $active = fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true);

        return [
            'village_id' => ['required', 'integer', Rule::exists(OrganizationUnit::class, 'row_id')->where(fn ($query) => $active($query)->where('type', 'village'))],
            'business_type_id' => ['required', 'integer', Rule::exists(BusinessType::class, 'row_id')->where($active)],
            'activity_type_id' => ['required', 'integer', Rule::exists(ActivityType::class, 'row_id')->where($active)],
            'group_level_id' => ['required', 'integer', Rule::exists(GroupLevel::class, 'row_id')->where($active)],
            'group_function_id' => ['required', 'integer', Rule::exists(GroupFunction::class, 'row_id')->where($active)],
            'name' => ['required', 'string', 'max:225'],
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'established_at' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'member_ids' => ['required', 'array', 'min:3'],
            'member_ids.*' => ['required', 'integer', 'distinct', Rule::exists(Member::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active')->whereNull('deleted_at'))],
            'chair_id' => ['required', 'integer', 'different:secretary_id', 'different:treasurer_id'],
            'secretary_id' => ['required', 'integer', 'different:chair_id', 'different:treasurer_id'],
            'treasurer_id' => ['required', 'integer', 'different:chair_id', 'different:secretary_id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $members = array_map('intval', (array) $this->input('member_ids', []));
            foreach (['chair_id', 'secretary_id', 'treasurer_id'] as $field) {
                if ($this->filled($field) && ! in_array((int) $this->input($field), $members, true)) {
                    $validator->errors()->add($field, 'Pengurus harus dipilih dari anggota kelompok.');
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $villageId = (int) $this->input('village_id');
            $officerMap = [
                'chair_id' => (int) $this->input('chair_id'),
                'secretary_id' => (int) $this->input('secretary_id'),
                'treasurer_id' => (int) $this->input('treasurer_id'),
            ];
            $memberToIndex = array_flip(array_map('intval', $members));
            $officerByMemberId = [];
            foreach ($officerMap as $field => $memberId) {
                if ($memberId > 0 && ! isset($officerByMemberId[$memberId])) {
                    $officerByMemberId[$memberId] = $field;
                }
            }

            $candidateIds = collect($members)
                ->merge(array_values($officerMap))
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $villageName = OrganizationUnit::query()->where('row_id', $villageId)->value('name') ?? 'desa kelompok';

            $offendingRows = DB::connection('tenant')
                ->table('members')
                ->join('people', fn ($join) => $join->on('people.row_id', '=', 'members.person_row_id')->whereColumn('people.tenant_id', 'members.tenant_id'))
                ->leftJoin('organization_units as villages', fn ($join) => $join->on('villages.row_id', '=', 'members.organization_unit_row_id')->whereColumn('villages.tenant_id', 'members.tenant_id'))
                ->whereIn('members.row_id', $candidateIds)
                ->where('members.organization_unit_row_id', '!=', $villageId)
                ->get(['members.row_id', 'people.full_name', 'people.national_identity_number', 'villages.name as village_name']);

            foreach ($offendingRows as $row) {
                $label = trim(($row->full_name ?? 'Tanpa nama').' ('.($row->national_identity_number ?? 'NIK kosong').') dari desa '.($row->village_name ?? 'tidak diketahui'));
                $message = "{$label} bukan berasal dari desa {$villageName}.";

                if (isset($officerByMemberId[$row->row_id])) {
                    $validator->errors()->add($officerByMemberId[$row->row_id], $message);
                }
                if (isset($memberToIndex[$row->row_id])) {
                    $validator->errors()->add('member_ids.'.$memberToIndex[$row->row_id], $message);
                } else {
                    $validator->errors()->add('member_ids', $message);
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'village_id' => 'desa',
            'business_type_id' => 'jenis usaha',
            'activity_type_id' => 'jenis kegiatan',
            'group_level_id' => 'tingkatan kelompok',
            'group_function_id' => 'fungsi kelompok',
            'name' => 'nama kelompok',
            'address' => 'alamat',
            'phone' => 'telepon',
            'established_at' => 'tanggal berdiri',
            'status' => 'status',
            'member_ids' => 'anggota kelompok',
            'member_ids.*' => 'anggota kelompok',
            'chair_id' => 'ketua',
            'secretary_id' => 'sekretaris',
            'treasurer_id' => 'bendahara',
        ];
    }
}
