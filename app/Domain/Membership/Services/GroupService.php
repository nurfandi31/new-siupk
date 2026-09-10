<?php

declare(strict_types=1);

namespace App\Domain\Membership\Services;

use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\GroupMember;
use App\Domain\Membership\Models\GroupOfficer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class GroupService
{
    public function create(array $data): Group
    {
        foreach (range(1, 3) as $attempt) {
            try {
                return DB::connection('tenant')->transaction(function () use ($data): Group {
                    $group = Group::query()->create([
                        ...$this->attributes($data),
                        'code' => $this->randomCode(),
                    ]);
                    $this->sync($group, $data);

                    return $group->load($this->relations());
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 3 || ! str_contains($exception->getMessage(), 'uq_groups_code')) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unable to create group.');
    }

    public function update(Group $group, array $data): Group
    {
        return DB::connection('tenant')->transaction(function () use ($group, $data): Group {
            $locked = Group::query()->lockForUpdate()->findOrFail($group->row_id);
            $locked->update($this->attributes($data));
            $this->sync($locked, $data);

            return $locked->fresh()->load($this->relations());
        });
    }

    private function attributes(array $data): array
    {
        return [
            'organization_unit_row_id' => $data['village_id'],
            'business_type_row_id' => $data['business_type_id'],
            'activity_type_row_id' => $data['activity_type_id'],
            'group_level_row_id' => $data['group_level_id'],
            'group_function_row_id' => $data['group_function_id'],
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'established_at' => $data['established_at'] ?? null,
            'status' => $data['status'],
        ];
    }

    private function sync(Group $group, array $data): void
    {
        $today = now()->toDateString();
        $memberIds = array_map('intval', $data['member_ids']);
        $active = $group->activeMemberships()->get()->keyBy('member_row_id');

        foreach ($active as $memberId => $membership) {
            if (! in_array((int) $memberId, $memberIds, true)) {
                $membership->update(['status' => 'inactive', 'left_at' => $today]);
            }
        }

        foreach ($memberIds as $memberId) {
            if ($active->has($memberId)) {
                continue;
            }

            $sameDay = $group->memberships()
                ->where('member_row_id', $memberId)
                ->whereDate('joined_at', $today)
                ->first();
            if ($sameDay) {
                $sameDay->update(['status' => 'active', 'left_at' => null]);
            } else {
                GroupMember::query()->create([
                    'group_row_id' => $group->row_id,
                    'member_row_id' => $memberId,
                    'joined_at' => $today,
                    'status' => 'active',
                ]);
            }
        }

        $officers = [
            'chair' => (int) $data['chair_id'],
            'secretary' => (int) $data['secretary_id'],
            'treasurer' => (int) $data['treasurer_id'],
        ];
        $current = $group->activeOfficers()->get()->keyBy('position');

        foreach ($officers as $position => $memberId) {
            $officer = $current->get($position);
            if ($officer && (int) $officer->member_row_id === $memberId) {
                continue;
            }
            $officer?->update(['ended_at' => $today]);
            GroupOfficer::query()->create([
                'group_row_id' => $group->row_id,
                'member_row_id' => $memberId,
                'position' => $position,
                'started_at' => $today,
            ]);
        }
    }

    private function randomCode(): string
    {
        $code = '';
        foreach (range(1, 14) as $_) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }

    private function relations(): array
    {
        return ['village', 'businessType', 'activityType', 'level', 'functionType', 'activeMemberships.member.person', 'activeOfficers.member.person'];
    }
}
