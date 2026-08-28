<?php

namespace App\Services;

use App\Models\PhoneDirectoryEntry;
use App\Models\User;
use App\Support\PersonName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Excel-ийн Овог/Нэр/утас/и-мэйл жагсаалтыг утасны бүртгэл болон нэвтрэх хэрэглэгчтэй тааруулна.
 */
class PhoneDirectoryStaffSyncer
{
    public const DEFAULT_ORG = 'Засаг даргын Тамгын газар';

    /**
     * @param  list<array{surname: string, given: string, mobile: string, email: string}>  $people
     * @return array{updated: int, created: int, users: int, skipped: int}
     */
    public function sync(array $people, bool $dryRun = false): array
    {
        $updated = 0;
        $created = 0;
        $users = 0;
        $skipped = 0;

        $apply = function () use ($people, $dryRun, &$updated, &$created, &$users, &$skipped): void {
            $entries = PhoneDirectoryEntry::query()->orderBy('id')->get();
            $accounts = User::query()->orderBy('id')->get();
            $usedEntryIds = [];

            foreach ($people as $person) {
                $short = PersonName::short(trim($person['surname'].' '.$person['given']))
                    ?: trim($person['given']);
                $phone = $person['mobile'] !== '' ? $person['mobile'] : null;
                $email = $person['email'] !== '' ? strtolower($person['email']) : null;

                if ($short === '') {
                    $skipped++;

                    continue;
                }

                $entry = $this->matchEntry($entries, $short, $phone, $usedEntryIds);

                if ($entry) {
                    $usedEntryIds[$entry->id] = true;
                    $oldPhone = User::normalizePhone($entry->mobile_phone);
                    $changed = $this->fillEntry($entry, $short, $phone);

                    if ($changed) {
                        if (! $dryRun) {
                            $entry->save();
                        }
                        $updated++;
                    }

                    if ($this->syncUser($accounts, $short, $phone, $email, $oldPhone, $dryRun)) {
                        $users++;
                    }

                    continue;
                }

                if (! $dryRun) {
                    $entry = $this->createEntry($short, $phone);
                    $entries->push($entry);
                    $usedEntryIds[$entry->id] = true;
                }
                $created++;

                if ($this->syncUser($accounts, $short, $phone, $email, null, $dryRun)) {
                    $users++;
                }
            }
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        return compact('updated', 'created', 'users', 'skipped');
    }

    /**
     * @param  Collection<int, PhoneDirectoryEntry>  $entries
     * @param  array<int, true>  $usedEntryIds
     */
    private function matchEntry(Collection $entries, string $short, ?string $phone, array $usedEntryIds): ?PhoneDirectoryEntry
    {
        $available = $entries->filter(fn (PhoneDirectoryEntry $e) => ! isset($usedEntryIds[$e->id]));

        if ($phone) {
            $byPhone = $available->filter(function (PhoneDirectoryEntry $e) use ($phone) {
                return User::normalizePhone($e->mobile_phone) === $phone
                    || User::normalizePhone($e->office_phone) === $phone;
            });

            if ($byPhone->count() === 1) {
                return $byPhone->first();
            }

            $named = $byPhone->first(fn (PhoneDirectoryEntry $e) => PersonName::short($e->person_name) === $short);
            if ($named) {
                return $named;
            }
        }

        $byShort = $available->filter(fn (PhoneDirectoryEntry $e) => PersonName::short($e->person_name) === $short);

        return $byShort->count() === 1 ? $byShort->first() : null;
    }

    private function fillEntry(PhoneDirectoryEntry $entry, string $short, ?string $phone): bool
    {
        $changed = false;

        if ($entry->person_name !== $short) {
            $entry->person_name = $short;
            $changed = true;
        }

        if ($phone && User::normalizePhone($entry->mobile_phone) !== $phone) {
            $entry->mobile_phone = $phone;
            $changed = true;
        }

        return $changed;
    }

    private function createEntry(string $short, ?string $phone): PhoneDirectoryEntry
    {
        $org = PhoneDirectoryEntry::query()
            ->where('org_name', self::DEFAULT_ORG)
            ->first()
            ?? PhoneDirectoryEntry::query()->where('category', 'heltes')->orderBy('org_order')->first();

        $orgName = $org?->org_name ?: self::DEFAULT_ORG;
        $orgOrder = (int) (PhoneDirectoryEntry::query()->where('org_name', $orgName)->min('org_order')
            ?: ((int) PhoneDirectoryEntry::query()->max('org_order') + 1));
        $sort = (int) PhoneDirectoryEntry::query()->where('org_name', $orgName)->max('sort_order') + 1;

        return PhoneDirectoryEntry::create([
            'org_name' => $orgName,
            'category' => $org?->category,
            'org_order' => max($orgOrder, 1),
            'sort_order' => $sort,
            'person_name' => $short,
            'mobile_phone' => $phone,
        ]);
    }

    /**
     * @param  Collection<int, User>  $accounts
     */
    private function syncUser(
        Collection $accounts,
        string $short,
        ?string $phone,
        ?string $email,
        ?string $oldPhone,
        bool $dryRun,
    ): bool {
        $user = $this->matchUser($accounts, $short, $phone, $email, $oldPhone);

        if (! $user) {
            return false;
        }

        $changed = false;

        if ($user->name !== $short) {
            $user->name = $short;
            $changed = true;
        }

        if ($phone && User::normalizePhone($user->phone) !== $phone && ! $this->phoneTaken($accounts, $phone, $user->id)) {
            $user->phone = $phone;
            $changed = true;
        }

        if ($email && strtolower((string) $user->email) !== $email && ! $this->emailTaken($accounts, $email, $user->id)) {
            $user->email = $email;
            $changed = true;
        }

        if ($changed && ! $dryRun) {
            $user->save();
        }

        return $changed;
    }

    /**
     * @param  Collection<int, User>  $accounts
     */
    private function matchUser(Collection $accounts, string $short, ?string $phone, ?string $email, ?string $oldPhone): ?User
    {
        if ($email) {
            $byEmail = $accounts->first(fn (User $u) => strtolower((string) $u->email) === $email);
            if ($byEmail) {
                return $byEmail;
            }
        }

        foreach (array_filter([$phone, $oldPhone]) as $tryPhone) {
            $byPhone = $accounts->filter(fn (User $u) => User::normalizePhone($u->phone) === $tryPhone);
            if ($byPhone->count() === 1) {
                return $byPhone->first();
            }
        }

        $byName = $accounts->filter(fn (User $u) => PersonName::short($u->name) === $short);

        return $byName->count() === 1 ? $byName->first() : null;
    }

    /**
     * @param  Collection<int, User>  $accounts
     */
    private function phoneTaken(Collection $accounts, string $phone, int $ignoreId): bool
    {
        return $accounts->contains(
            fn (User $u) => $u->id !== $ignoreId && User::normalizePhone($u->phone) === $phone
        );
    }

    /**
     * @param  Collection<int, User>  $accounts
     */
    private function emailTaken(Collection $accounts, string $email, int $ignoreId): bool
    {
        return $accounts->contains(
            fn (User $u) => $u->id !== $ignoreId && strtolower((string) $u->email) === $email
        );
    }
}
