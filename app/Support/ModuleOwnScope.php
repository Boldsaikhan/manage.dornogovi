<?php

namespace App\Support;

use App\Models\Award;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * «Хамааралтай» эрх — зөвхөн тухайн албан хаагчид холбоотой бүртгэл.
 */
class ModuleOwnScope
{
    /**
     * @param  Builder|Relation  $query
     */
    public static function apply(Builder|Relation $query, User $user, string $moduleKey): Builder
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        if ($user->is_admin || ! ModuleAccess::scopeOwnOnly($user, $moduleKey)) {
            return $builder;
        }

        $type = ModuleAccess::find($moduleKey)['own_scope'] ?? null;

        if (! $type) {
            return $builder;
        }

        return self::applyByType($builder, $user, $moduleKey, $type);
    }

    /**
     * Модулийн хамааралтай шүүлтийг тухайн модулийн эрхээс үл хамааран хэрэглэнэ
     * (жнь: самбарын «Харах (хамааралтай)»).
     *
     * @param  Builder|Relation  $query
     */
    public static function applyOwnRecords(Builder|Relation $query, User $user, string $moduleKey): Builder
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        if ($user->is_admin) {
            return $builder;
        }

        $type = ModuleAccess::find($moduleKey)['own_scope'] ?? null;

        if (! $type || $type === 'dashboard') {
            return $builder;
        }

        return self::applyByType($builder, $user, $moduleKey, $type);
    }

    public static function allows(User $user, string $moduleKey, Model $record): bool
    {
        if ($user->is_admin || ! ModuleAccess::scopeOwnOnly($user, $moduleKey)) {
            return true;
        }

        $type = ModuleAccess::find($moduleKey)['own_scope'] ?? null;

        if (! $type) {
            return false;
        }

        return self::allowsByType($user, $moduleKey, $record, $type);
    }

    private static function applyByType(Builder $builder, User $user, string $moduleKey, string $type): Builder
    {
        return match ($type) {
            'user_id' => $builder->where('user_id', $user->id),
            'created_by' => $builder->where('created_by', $user->id),
            'public_read_own_write' => self::applyPublicReadOwnWrite($builder, $user, $moduleKey),
            'lead_user_id' => $builder->where('lead_user_id', $user->id),
            'person_name' => self::applyPersonNameScope($builder, $user, 'person_name'),
            'award_person' => self::applyAwardPersonScope($builder, $user),
            'task_assignee' => self::applyTaskAssigneeScope($builder, $user),
            default => $builder,
        };
    }

    private static function allowsByType(User $user, string $moduleKey, Model $record, string $type): bool
    {
        return match ($type) {
            'user_id' => (int) $record->user_id === (int) $user->id,
            'created_by' => (int) $record->created_by === (int) $user->id,
            'public_read_own_write' => self::allowsPublicReadOwnWrite($user, $moduleKey, $record),
            'lead_user_id' => (int) $record->lead_user_id === (int) $user->id,
            'person_name' => PersonName::matchesUser($user, $record->person_name ?? null),
            'award_person' => $record instanceof Award && self::awardMatchesUser($user, $record),
            'task_assignee' => PersonName::matchesUser($user, $record->responsible ?? null)
                || PersonName::matchesUser($user, $record->collaborator ?? null),
            default => false,
        };
    }

    /**
     * Үүрэг даалгаврыг responsible/collaborator нэрээр шүүнэ (эрхээс үл хамааран).
     *
     * @param  Builder|Relation  $query
     */
    public static function restrictTasksToAssignee(Builder|Relation $query, User $user): Builder
    {
        $builder = $query instanceof Relation ? $query->getQuery() : $query;

        return self::applyTaskAssigneeScope($builder, $user);
    }

    /**
     * «Удирдах (хамааралтай)» эрхтэй хэрэглэгч зөвхөн өөрт хамааралтай мөр үүсгэнэ.
     *
     * @param  array<string, mixed>  $data
     */
    public static function assertCanCreate(User $user, string $moduleKey, array $data = []): void
    {
        if ($user->is_admin || ! ModuleAccess::scopeOwnOnly($user, $moduleKey)) {
            return;
        }

        $type = ModuleAccess::find($moduleKey)['own_scope'] ?? null;

        abort_unless($type, 403, 'Энэ модульд хамааралтай бүртгэл үүсгэх боломжгүй.');

        $allowed = match ($type) {
            'person_name' => PersonName::matchesUser($user, $data['person_name'] ?? $user->name),
            'award_person' => self::awardDataMatchesUser($user, $data),
            default => true,
        };

        abort_unless($allowed, 403, 'Зөвхөн өөрт хамааралтай бүртгэл үүсгэнэ.');
    }

    /**
     * Журам гэх мэт: харах нь бүгдийг, оруулах/удирдах нь зөвхөн өөрийнх.
     */
    private static function applyPublicReadOwnWrite(Builder $query, User $user, string $moduleKey): Builder
    {
        if (ModuleAccess::level($user, $moduleKey) === 'view_own') {
            return $query;
        }

        return $query->where('created_by', $user->id);
    }

    private static function allowsPublicReadOwnWrite(User $user, string $moduleKey, Model $record): bool
    {
        if (ModuleAccess::level($user, $moduleKey) === 'view_own') {
            return true;
        }

        return (int) ($record->created_by ?? 0) === (int) $user->id;
    }

    private static function applyPersonNameScope(Builder $query, User $user, string $column): Builder
    {
        return self::applyNameLikeScope($query, $user, [$column]);
    }

    private static function applyAwardPersonScope(Builder $query, User $user): Builder
    {
        [$surname, $given] = PersonName::splitUserName($user);

        return $query->where(function (Builder $inner) use ($surname, $given, $user) {
            if ($given !== '') {
                $inner->where('given_name', 'like', '%'.$given.'%');
            }

            if ($surname !== '') {
                $inner->where(function (Builder $nested) use ($surname) {
                    $nested->where('surname', 'like', '%'.$surname.'%')
                        ->orWhere('surname', 'like', mb_substr($surname, 0, 1).'.%');
                });
            }

            foreach (PersonName::matchPatterns($user) as $pattern) {
                $inner->orWhereRaw("concat(coalesce(surname,''), ' ', coalesce(given_name,'')) like ?", ['%'.$pattern.'%']);
            }
        });
    }

    private static function applyTaskAssigneeScope(Builder $query, User $user): Builder
    {
        return self::applyNameLikeScope($query, $user, ['responsible', 'collaborator']);
    }

    /**
     * @param  list<string>  $columns
     */
    private static function applyNameLikeScope(Builder $query, User $user, array $columns): Builder
    {
        $patterns = PersonName::matchPatterns($user);

        if ($patterns === [] || $columns === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($columns, $patterns): void {
            foreach ($columns as $column) {
                foreach ($patterns as $pattern) {
                    $inner->orWhere($column, 'like', '%'.$pattern.'%');
                    $compact = preg_replace('/\s+/u', '', $pattern) ?? '';
                    if ($compact !== '' && $compact !== $pattern) {
                        $inner->orWhereRaw('REPLACE('.$column.", ' ', '') like ?", ['%'.$compact.'%']);
                    }
                }
            }
        });
    }

    private static function awardMatchesUser(User $user, Award $award): bool
    {
        return self::awardDataMatchesUser($user, [
            'surname' => $award->surname,
            'given_name' => $award->given_name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function awardDataMatchesUser(User $user, array $data): bool
    {
        [$surname, $given] = PersonName::splitUserName($user);
        $rowGiven = trim((string) ($data['given_name'] ?? ''));
        $rowSurname = trim((string) ($data['surname'] ?? ''));

        if ($given !== '' && $rowGiven !== '' && ! str_contains(mb_strtolower($rowGiven), mb_strtolower($given))) {
            return false;
        }

        if ($surname !== '' && $rowSurname !== '') {
            $shortSurname = mb_substr($surname, 0, 1);

            return str_contains(mb_strtolower($rowSurname), mb_strtolower($surname))
                || str_starts_with(mb_strtolower($rowSurname), mb_strtolower($shortSurname));
        }

        return $given !== '' && $rowGiven !== '';
    }
}
