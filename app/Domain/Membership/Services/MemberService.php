<?php

declare(strict_types=1);

namespace App\Domain\Membership\Services;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberAddress;
use App\Domain\Membership\Models\MemberBusiness;
use App\Domain\Membership\Models\MemberGuarantor;
use App\Domain\Membership\Models\Person;
use Illuminate\Support\Facades\DB;

final class MemberService
{
    public function create(array $data, int $userId): Member
    {
        return DB::connection('tenant')->transaction(function () use ($data, $userId): Member {
            $person = Person::query()->create($this->personAttributes($data));
            $member = Member::query()->create([
                'person_row_id' => $person->row_id,
                'organization_unit_row_id' => $data['village_id'],
                'member_number' => $data['nik'],
                'registered_at' => $data['registered_at'],
                'status' => $data['status'],
                'registered_by_user_id' => $userId,
            ]);

            $this->syncChildren($member, $data);

            return $member->load(['person', 'village', 'address', 'business', 'guarantor.person']);
        });
    }

    public function update(Member $member, array $data): Member
    {
        return DB::connection('tenant')->transaction(function () use ($member, $data): Member {
            $member->person()->update($this->personAttributes($data));
            $member->update([
                'organization_unit_row_id' => $data['village_id'],
                'member_number' => $data['nik'],
                'registered_at' => $data['registered_at'],
                'status' => $data['status'],
            ]);

            $this->syncChildren($member, $data);

            return $member->fresh()->load(['person', 'village', 'address', 'business', 'guarantor.person']);
        });
    }

    private function personAttributes(array $data): array
    {
        return [
            'national_identity_number' => $data['nik'],
            'family_card_number' => $data['family_card_number'] ?? null,
            'full_name' => $data['name'],
            'gender' => $data['gender'],
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];
    }

    private function syncChildren(Member $member, array $data): void
    {
        $address = $member->address()->first();
        if ($address === null) {
            $address = new MemberAddress(['member_row_id' => $member->row_id]);
        }
        $address->fill([
            'address' => $data['address'],
            'village_code' => null,
            'type' => 'home',
            'is_primary' => true,
        ])->save();

        $business = $member->business()->first();
        if (($data['has_business'] ?? false) === true) {
            $business ??= new MemberBusiness(['member_row_id' => $member->row_id]);
            $business->fill([
                'name' => $data['business_name'],
                'description' => $data['business_description'] ?? null,
                'is_active' => true,
            ])->save();
        } elseif ($business !== null) {
            $business->delete();
        }

        $guarantor = $member->guarantor()->with('person')->first();
        if (($data['has_guarantor'] ?? false) === true) {
            $person = $guarantor?->person;
            if ($person === null) {
                $person = Person::query()->create([
                    'national_identity_number' => $data['guarantor_nik'],
                    'full_name' => $data['guarantor_name'],
                ]);
            } else {
                $person->update([
                    'national_identity_number' => $data['guarantor_nik'],
                    'full_name' => $data['guarantor_name'],
                ]);
            }

            $guarantor ??= new MemberGuarantor(['member_row_id' => $member->row_id]);
            $guarantor->fill([
                'guarantor_person_row_id' => $person->row_id,
                'relationship_type' => $data['guarantor_relationship'],
            ])->save();
        } elseif ($guarantor !== null) {
            $person = $guarantor->person;
            $guarantor->delete();
            if ($person !== null && $person->memberships()->doesntExist() && $person->guarantors()->doesntExist()) {
                $person->delete();
            }
        }
    }
}
