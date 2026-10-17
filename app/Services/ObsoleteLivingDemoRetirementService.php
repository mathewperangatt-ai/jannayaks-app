<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use App\Support\ObsoleteLivingDemos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Narrowly retires the seven obsolete short living demos from public listings.
 * Requires an exact match on demo-domain email and full_name. Never touches
 * other demos, memorials, or real member accounts.
 */
class ObsoleteLivingDemoRetirementService
{
    /**
     * @return array{retired: list<array<string, mixed>>, skipped: list<array<string, mixed>>, missing: list<array<string, mixed>>}
     */
    public function retire(bool $dryRun = false): array
    {
        $retired = [];
        $skipped = [];
        $missing = [];

        foreach (ObsoleteLivingDemos::targets() as $target) {
            $user = User::query()->where('email', $target['email'])->first();
            if (! $user) {
                $missing[] = $target + ['reason' => 'demo user not found'];

                continue;
            }

            $profile = Profile::query()->where('user_id', $user->id)->first();
            if (! $profile) {
                $missing[] = $target + ['reason' => 'demo profile not found'];

                continue;
            }

            if ((string) $profile->full_name !== $target['full_name']
                && (string) $profile->display_name !== $target['full_name']) {
                $skipped[] = $target + [
                    'reason' => 'name mismatch',
                    'profile_id' => $profile->id,
                    'actual_full_name' => $profile->full_name,
                ];

                continue;
            }

            $alreadyInactive = $profile->status === 'inactive' && $profile->unpublished_at !== null;
            if ($alreadyInactive) {
                $skipped[] = $target + [
                    'reason' => 'already retired',
                    'profile_id' => $profile->id,
                ];

                continue;
            }

            if (! $dryRun) {
                DB::transaction(function () use ($profile): void {
                    $locked = Profile::query()->whereKey($profile->id)->lockForUpdate()->firstOrFail();
                    $locked->forceFill([
                        'status' => 'inactive',
                        'unpublished_at' => $locked->unpublished_at ?? now(),
                    ])->save();
                });
            }

            $retired[] = $target + [
                'profile_id' => $profile->id,
                'previous_status' => $profile->status,
                'dry_run' => $dryRun,
            ];
        }

        return compact('retired', 'skipped', 'missing');
    }

    /**
     * @return Collection<int, Profile>
     */
    public function retainedLivingDemoProfiles(): Collection
    {
        return Profile::query()
            ->where('status', 'published')
            ->whereNull('unpublished_at')
            ->whereHas('user', function ($query): void {
                $query->where('email', 'like', 'demo.%@jannayaks.internal')
                    ->whereNotIn('email', ObsoleteLivingDemos::emails());
            })
            ->orderBy('id')
            ->get();
    }
}
