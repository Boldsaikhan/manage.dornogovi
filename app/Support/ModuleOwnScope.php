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

        return match ($type) {
            'user_id' => $builder->where('user_id', $user->id),
            'created_by' => $builder->where('created_by', $user->id),
            'lead_user_id' => $builder->where('lead_user_id', $user->id),
            'person_name' => self::applyPersonNameScope($builder, $user, 'person_name'),
            'award_person' => self::applyAwardPersonScope($builder, $user),
            'task_assignee' => self::applyTaskAssigneeScope($builder, $user),
            default => $builder,
        };
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

        return match ($type) {
            'user_id' => (int) $record->user_id === (int) $user->id,
            'created_by' => (int) $record->created_by === (int) $user->id,
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
        if ($user->is_admin || ! ModuleAccess::manageOwnOnly($user, $moduleKey)) {
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

    private static function applyPersonNameScope(Builder $query, User $user, string $column): Builder
    {
        $patterns = PersonName::matchPatterns($user);

        return $query->where(function (Builder $inner) use ($column, $patterns) {
            foreach ($patterns as $pattern) {
                $inner->orWhere($column, 'like', '%'.$pattern.'%');
            }
        });
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
        $patterns = PersonName::matchPatterns($user);

        return $query->where(function (Builder $inner) use ($patterns) {
            foreach ($patterns as $pattern) {
                $inner->orWhere('responsible', 'like', '%'.$pattern.'%')
                    ->orWhere('collaborator', 'like', '%'.$pattern.'%');
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
