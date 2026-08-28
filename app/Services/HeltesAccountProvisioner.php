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
use Illuminate\Support\Facades\Schema;

/**
 * Утасны жагсаалтын «Хэлтэс» ангиллын албан хаагчдад нэвтрэх эрх үүсгэнэ.
 *
 * Нэвтрэх нэр: гар утас.
 * И-мэйл: латин нэр @dornogovi.gov.mn (жнь: badral@dornogovi.gov.mn).
 * Нууц үг: ZDTG@2026 (супер админ биш).
 */
class HeltesAccountProvisioner
{
    public const STAFF_LOGIN_PASSWORD = 'ZDTG@2026';

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
     * Хуучин phone@staff.dornogovi.gov.mn хаягийг нэр@dornogovi.gov.mn болгоно.
     * Нууц үгийг өөрчлөхгүй.
     */
    public function syncStaffEmails(): int
    {
        $updated = 0;
        $seenPhones = [];

        foreach ($this->entries() as $entry) {
            $creds = $this->credentials($entry);

            if ($creds === null || isset($seenPhones[$creds['phone']])) {
                continue;
            }

            $seenPhones[$creds['phone']] = true;

            $user = User::query()->where('phone', $creds['phone'])->first();

            if ($user === null || $user->is_admin) {
                continue;
            }

            $email = $this->uniqueEmail($creds['latin'], $user->id);

            if ($user->email === $email) {
                continue;
            }

            $user->email = $email;
            $user->save();
            $updated++;
        }

        $leftovers = User::query()
            ->where('is_admin', false)
            ->where('email', 'like', '%@staff.dornogovi.gov.mn')
            ->get();

        foreach ($leftovers as $user) {
            $short = PersonName::short($user->name) ?: trim((string) $user->name);
            $latin = $this->latinGivenName($short !== '' ? $short : 'User');
            $email = $this->uniqueEmail($latin, $user->id);

            if ($user->email === $email) {
                continue;
            }

            $user->email = $email;
            $user->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * @return array{name: string, phone: string, password: string, position: string, latin: string}|null
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

        $latin = $this->latinGivenName($name);

        return [
            'name' => $name,
            'phone' => $phone,
            'password' => self::passwordFromPhone($phone),
            'position' => trim((string) $entry->position),
            'latin' => $latin,
        ];
    }

    /** Албан хаагчийн нэвтрэх нууц үг. */
    public static function passwordFromPhone(string $phone): string
    {
        return self::STAFF_LOGIN_PASSWORD;
    }

    /**
     * Хандах эрхтэй (админ биш) бүх хэрэглэгчийн нууц үгийг ZDTG@2026 болгоно.
     */
    public function syncStaffPasswords(): int
    {
        return $this->setStaffLoginPasswords(self::STAFF_LOGIN_PASSWORD);
    }

    /** Бүх хэрэглэгчийн нэвтрэх нууц үгийг нэг утгаар солино. */
    public function setAllLoginPasswords(string $plain): int
    {
        $updated = 0;

        User::query()
            ->orderBy('id')
            ->each(function (User $user) use ($plain, &$updated): void {
                $user->password = $plain;
                $user->setRememberToken(null);
                $user->save();
                $updated++;
            });

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->truncate();
        }

        return $updated;
    }

    /** Зөвхөн супер админ (is_admin) хэрэглэгчдийн нэвтрэх нууц үгийг солино. */
    public function setAdminLoginPasswords(string $plain): int
    {
        $updated = 0;
        $ids = [];

        User::query()
            ->where('is_admin', true)
            ->orderBy('id')
            ->each(function (User $user) use ($plain, &$updated, &$ids): void {
                $user->password = $plain;
                $user->setRememberToken(null);
                $user->save();
                $ids[] = $user->id;
                $updated++;
            });

        if ($ids !== [] && Schema::hasTable('sessions')) {
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
        }

        return $updated;
    }

    /** Зөвхөн супер админ биш хэрэглэгчдийн нэвтрэх нууц үгийг солино. */
    public function setStaffLoginPasswords(string $plain): int
    {
        $updated = 0;
        $ids = [];

        User::query()
            ->where('is_admin', false)
            ->orderBy('id')
            ->each(function (User $user) use ($plain, &$updated, &$ids): void {
                $user->password = $plain;
                $user->setRememberToken(null);
                $user->save();
                $ids[] = $user->id;
                $updated++;
            });

        if ($ids !== [] && Schema::hasTable('sessions')) {
            DB::table('sessions')->whereIn('user_id', $ids)->delete();
        }

        return $updated;
    }

    /**
     * «Ц.Сансармаа» → Sansarmaa, «А.Номин» → Nomin
     */
    public function latinGivenName(string $shortName): string
    {
        $name = trim($shortName);

        if (str_contains($name, '.')) {
            $name = trim((string) substr($name, strrpos($name, '.') + 1));
        }

        $latin = $this->cyrillicToLatin($name);
        $latin = preg_replace('/[^A-Za-z]/', '', $latin) ?? '';

        if ($latin === '') {
            $latin = 'User';
        }

        return ucfirst(strtolower($latin));
    }

    private function cyrillicToLatin(string $text): string
    {
        $map = [
            'А' => 'A', 'а' => 'a', 'Б' => 'B', 'б' => 'b', 'В' => 'V', 'в' => 'v',
            'Г' => 'G', 'г' => 'g', 'Д' => 'D', 'д' => 'd', 'Е' => 'E', 'е' => 'e',
            'Ё' => 'Yo', 'ё' => 'yo', 'Ж' => 'J', 'ж' => 'j', 'З' => 'Z', 'з' => 'z',
            'И' => 'I', 'и' => 'i', 'Й' => 'I', 'й' => 'i', 'К' => 'K', 'к' => 'k',
            'Л' => 'L', 'л' => 'l', 'М' => 'M', 'м' => 'm', 'Н' => 'N', 'н' => 'n',
            'О' => 'O', 'о' => 'o', 'Ө' => 'O', 'ө' => 'o', 'П' => 'P', 'п' => 'p',
            'Р' => 'R', 'р' => 'r', 'С' => 'S', 'с' => 's', 'Т' => 'T', 'т' => 't',
            'У' => 'U', 'у' => 'u', 'Ү' => 'U', 'ү' => 'u', 'Ф' => 'F', 'ф' => 'f',
            'Х' => 'Kh', 'х' => 'kh', 'Ц' => 'Ts', 'ц' => 'ts', 'Ч' => 'Ch', 'ч' => 'ch',
            'Ш' => 'Sh', 'ш' => 'sh', 'Щ' => 'Sh', 'щ' => 'sh', 'Ъ' => '', 'ъ' => '',
            'Ы' => 'Y', 'ы' => 'y', 'Ь' => '', 'ь' => '', 'Э' => 'E', 'э' => 'e',
            'Ю' => 'Yu', 'ю' => 'yu', 'Я' => 'Ya', 'я' => 'ya',
        ];

        return strtr($text, $map);
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
     * @param  array{name: string, phone: string, password: string, position: string, latin: string}  $creds
     */
    private function createUser(array $creds, PhoneDirectoryEntry $entry, bool $isHead): void
    {
        $user = User::create([
            'name' => $creds['name'],
            'email' => $this->uniqueEmail($creds['latin']),
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
     * @param  array{name: string, phone: string, password: string, position: string, latin: string}  $creds
     */
    private function updateExisting(User $user, array $creds, PhoneDirectoryEntry $entry, bool $isHead): void
    {
        $user->fill([
            'name' => $creds['name'],
            'department_id' => $this->resolveDepartment($entry->org_name) ?? $user->department_id,
            'position' => $creds['position'] !== '' ? $creds['position'] : $user->position,
        ]);

        if (! $user->is_admin) {
            $user->email = $this->uniqueEmail($creds['latin'], $user->id);
            $user->password = $creds['password'];
            $user->is_department_head = $isHead;
            $user->is_specialist = ! $isHead;
        }

        $user->save();

        if (! $user->is_admin) {
            $this->applyRolePermissions($user, $isHead ? 'department_head' : 'specialist');
        }
    }

    private function applyRolePermissions(User $user, string $role): void
    {
        $map = RolePermission::map()[$role] ?? RolePermission::DEFAULTS[$role] ?? [];

        $user->modulePermissions()->delete();

        foreach ($map as $key => $level) {
            if (! ModuleAccess::find($key) || ! in_array($level, ModuleAccess::LEVELS, true)) {
                continue;
            }

            $user->modulePermissions()->create([
                'module_key' => $key,
                'level' => $level,
            ]);
        }
    }

    private function uniqueEmail(string $latin, ?int $ignoreUserId = null): string
    {
        $local = strtolower($latin);
        $local = preg_replace('/[^a-z]/', '', $local) ?? '';

        if ($local === '') {
            $local = 'user';
        }

        $taken = function (string $email) use ($ignoreUserId): bool {
            $query = User::query()->where('email', $email);

            if ($ignoreUserId !== null) {
                $query->where('id', '!=', $ignoreUserId);
            }

            return $query->exists();
        };

        $base = $local.'@dornogovi.gov.mn';

        if (! $taken($base)) {
            return $base;
        }

        $i = 2;
        do {
            $email = $local.$i.'@dornogovi.gov.mn';
            $i++;
        } while ($taken($email));

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
