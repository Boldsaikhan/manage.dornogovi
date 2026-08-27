<?php

namespace App\Services;

use App\Models\Department;
use App\Models\PhoneDirectoryEntry;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\ModuleAccess;
use App\Support\PersonName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Утасны жагсаалтын «Хэлтэс» ангиллын албан хаагчдад нэвтрэх эрх үүсгэнэ.
 *
 * Нэвтрэх нэр: гар утас. Нууц үг: нэр + утасны сүүлийн 4 орон.
 */
class HeltesAccountProvisioner
{
    /**
     * @return array{created: int, updated: int, skipped: list<array{name: string, reason: string}>, dry_run: bool}
     */
    public function run(bool $dryRun = false): array
    {
        $created = 0;
        $updated = 0;
        $skipped = [];
        $seenPhones = [];

        $apply = function () use ($dryRun, &$created, &$updated, &$skipped, &$seenPhones): void {
            foreach ($this->entries() as $entry) {
                $result = $this->provisionOne($entry, $seenPhones, $dryRun);

                if ($result['status'] === 'created') {
                    $created++;
                } elseif ($result['status'] === 'updated') {
                    $updated++;
                } else {
                    $skipped[] = [
                        'name' => $result['name'],
                        'reason' => $result['reason'],
                    ];
                }
            }
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ];
    }

    public function eligibleCount(): int
    {
        $seen = [];
        $count = 0;

        foreach ($this->entries() as $entry) {
            $creds = $this->credentials($entry);

            if ($creds === null || isset($seen[$creds['phone']])) {
                continue;
            }

            $seen[$creds['phone']] = true;
            $count++;
        }

        return $count;
    }

    /**
     * @return array{name: string, phone: string, password: string, position: string}|null
     */
    public function credentials(PhoneDirectoryEntry $entry): ?array
    {
        $full = trim((string) $entry->person_name);
        $name = PersonName::short($full) ?: $full;

        if ($name === '' || (! PersonName::isPerson($full) && ! PersonName::isPerson($name))) {
            return null;
        }

        $rawPhone = PhoneDirectoryEntry::preferredPhone($entry->mobile_phone, $entry->office_phone);
        $phone = User::normalizePhone($rawPhone);

        if ($phone === null || strlen($phone) < 4) {
            return null;
        }

        $last4 = substr($phone, -4);
        $password = $name.$last4;

        if (mb_strlen($password) < 8) {
            $password .= $phone;
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'password' => $password,
            'position' => trim((string) $entry->position),
        ];
    }

    /**
     * @return Collection<int, PhoneDirectoryEntry>
     */
    private function entries(): Collection
    {
        return PhoneDirectoryEntry::query()
            ->where('category', 'heltes')
            ->orderBy('org_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, true>  $seenPhones
     * @return array{status: string, name: string, reason: string}
     */
    private function provisionOne(PhoneDirectoryEntry $entry, array &$seenPhones, bool $dryRun): array
    {
        $label = trim((string) $entry->person_name) ?: '#'.$entry->id;
        $creds = $this->credentials($entry);

        if ($creds === null) {
            return ['status' => 'skipped', 'name' => $label, 'reason' => 'Нэр эсвэл утас дутуу'];
        }

        if (isset($seenPhones[$creds['phone']])) {
            return ['status' => 'skipped', 'name' => $creds['name'], 'reason' => 'Давхардсан утас'];
        }

        $seenPhones[$creds['phone']] = true;

        $existing = User::query()->where('phone', $creds['phone'])->first();
        $isHead = $this->isDepartmentHead($creds['position']);

        if ($dryRun) {
            return [
                'status' => $existing ? 'updated' : 'created',
                'name' => $creds['name'],
                'reason' => '',
            ];
        }

        if ($existing) {
            $this->updateExisting($existing, $creds, $entry, $isHead);

            return ['status' => 'updated', 'name' => $creds['name'], 'reason' => ''];
        }

        $this->createUser($creds, $entry, $isHead);

        return ['status' => 'created', 'name' => $creds['name'], 'reason' => ''];
    }

    /**
     * @param  array{name: string, phone: string, password: string, position: string}  $creds
     */
    private function createUser(array $creds, PhoneDirectoryEntry $entry, bool $isHead): void
    {
        $user = User::create([
            'name' => $creds['name'],
            'email' => $this->uniqueEmail($creds['phone']),
            'phone' => $creds['phone'],
            'password' => $creds['password'],
            'email_verified_at' => now(),
            'is_admin' => false,
            'department_id' => $this->resolveDepartment($entry->org_name),
            'position' => $creds['position'] !== '' ? $creds['position'] : null,
            'is_department_head' => $isHead,
            'is_specialist' => ! $isHead,
        ]);

        $this->applyRolePermissions($user, $isHead ? 'department_head' : 'specialist');
    }

    /**
     * @param  array{name: string, phone: string, password: string, position: string}  $creds
     */
    private function updateExisting(User $user, array $creds, PhoneDirectoryEntry $entry, bool $isHead): void
    {
        $user->fill([
            'name' => $creds['name'],
            'department_id' => $this->resolveDepartment($entry->org_name) ?? $user->department_id,
            'position' => $creds['position'] !== '' ? $creds['position'] : $user->position,
        ]);

        if (! $user->is_admin) {
            $user->password = $creds['password'];
            $user->is_department_head = $isHead;
            $user->is_specialist = ! $isHead;
        }

        $user->save();

        if (! $user->is_admin && $user->modulePermissions()->count() === 0) {
            $this->applyRolePermissions($user, $isHead ? 'department_head' : 'specialist');
        }
    }

    private function applyRolePermissions(User $user, string $role): void
    {
        $map = RolePermission::map()[$role] ?? RolePermission::DEFAULTS[$role] ?? [];

        $user->modulePermissions()->delete();

        foreach ($map as $key => $level) {
            if (! ModuleAccess::find($key) || ! in_array($level, ['view', 'manage'], true)) {
                continue;
            }

            $user->modulePermissions()->create([
                'module_key' => $key,
                'level' => $level,
            ]);
        }
    }

    private function uniqueEmail(string $phone): string
    {
        $base = $phone.'@staff.dornogovi.gov.mn';

        if (! User::query()->where('email', $base)->exists()) {
            return $base;
        }

        $i = 1;
        do {
            $email = $phone.'.'.$i.'@staff.dornogovi.gov.mn';
            $i++;
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }

    private function resolveDepartment(?string $orgName): ?int
    {
        $orgName = trim((string) $orgName);

        if ($orgName === '') {
            return null;
        }

        $existing = Department::query()->orderBy('id')->get();
        $needle = mb_strtolower($orgName);

        foreach ($existing as $dept) {
            $name = mb_strtolower(trim((string) $dept->name));
            if ($name === $needle) {
                return $dept->id;
            }
        }

        foreach ($existing as $dept) {
            $name = mb_strtolower(trim((string) $dept->name));
            if ($name !== '' && (str_contains($needle, $name) || str_contains($name, $needle))) {
                return $dept->id;
            }
        }

        $dept = Department::create([
            'name' => $orgName,
            'sort_order' => (int) Department::query()->max('sort_order') + 1,
            'is_active' => true,
        ]);

        return $dept->id;
    }

    private function isDepartmentHead(string $position): bool
    {
        $normalized = mb_strtolower(trim($position));

        return $normalized !== ''
            && str_ends_with($normalized, 'хэлтсийн дарга')
            && ! str_contains($normalized, 'орлогч');
    }
}
