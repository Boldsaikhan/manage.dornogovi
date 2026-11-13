<?php

namespace App\Support;

use App\Models\Department;
use App\Models\ReportRowEdit;
use App\Models\User;

/**
 * Тайлангийн мөрүүдийг бэлтгэнэ: JSON эх өгөгдөл + хэрэглэгчийн засвар,
 * дараа нь харах эрхийн дагуу шүүнэ.
 */
class ReportRows
{
    /**
     * Засвар нэгтгэсэн мөрүүд. Мөр бүрт JSON доторх байрлалыг (`_index`) хадгална.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function merged(string $reportKey): array
    {
        $rows = ReportsData::rows($reportKey);
        $edits = ReportRowEdit::query()->where('report_key', $reportKey)->get();
        $departments = Department::query()->pluck('name', 'id');

        $byRow = [];

        foreach ($edits as $edit) {
            $value = $edit->column_key === ReportRowEdit::DEPARTMENT_COLUMN && $edit->department_id
                ? ($departments[$edit->department_id] ?? $edit->value)
                : $edit->value;

            $byRow[$edit->row_index][$edit->column_key] = $value;

            if ($edit->column_key === ReportRowEdit::DEPARTMENT_COLUMN) {
                $byRow[$edit->row_index]['department_id'] = $edit->department_id;
            }
        }

        $base = array_values($rows);
        $maxEdit = $edits->max('row_index');
        $length = max(count($base), $maxEdit === null ? 0 : ((int) $maxEdit) + 1);
        $out = [];

        for ($index = 0; $index < $length; $index++) {
            $row = is_array($base[$index] ?? null) ? $base[$index] : [];
            $row['_index'] = $index;
            $row['department_id'] = null;

            $out[] = ReportSpan::apply(array_merge($row, $byRow[$index] ?? []));
        }

        return $out;
    }

    /**
     * JSON эх мөр болон файлаар нэмсэн мөрийн нийт тоо.
     * Дараагийн шинэ мөр энэ индекст эхэлнэ.
     */
    public static function length(string $reportKey): int
    {
        $base = count(ReportsData::rows($reportKey));
        $maxEdit = ReportRowEdit::query()->where('report_key', $reportKey)->max('row_index');

        return max($base, $maxEdit === null ? 0 : ((int) $maxEdit) + 1);
    }

    /**
     * Харах эрхийн дагуу мөрийг шүүнэ.
     *
     * - Хэлтсийн / хамааралтай удирдлага: өөрийн хэлтсийн мөр
     *   (хэлтэс сонгоогүйг нь хуваарилахын тулд харна).
     * - Хамааралтай харах/оруулах албан хаагч: зөвхөн өөрт оноогдсон мөр.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function visibleTo(array $rows, ?User $user): array
    {
        if (! $user || $user->is_admin) {
            return $rows;
        }

        // Хэлтсийн болон хамааралтай удирдлага — хэлтсээрээ шүүнэ.
        if (ModuleAccess::scopeUnitOnly($user, 'reports')
            || ModuleAccess::manageOwnOnly($user, 'reports')) {
            $user->loadMissing('department:id,name');

            return array_values(array_filter(
                $rows,
                static fn (array $row) => self::rowMatchesDepartment($row, $user),
            ));
        }

        // Албан хаагч — зөвхөн өөрт хамааралтай (оноогдсон) заалт.
        if (ModuleAccess::scopeOwnOnly($user, 'reports')) {
            return array_values(array_filter(
                $rows,
                static fn (array $row) => PersonName::matchesUser(
                    $user,
                    (string) ($row['assignee'] ?? ''),
                ),
            ));
        }

        return $rows;
    }

    /**
     * Мөр хэрэглэгчийн хэлтэст хамаарах эсэх.
     *
     * department_id эсвэл хэлтсийн нэрээр тааруулна. Хоёулаа хоосон бол
     * эзэнгүй мөр — удирдлага хуваарилахын тулд харна.
     */
    private static function rowMatchesDepartment(array $row, User $user): bool
    {
        $departmentId = $user->department_id;

        if ($departmentId !== null
            && (int) ($row['department_id'] ?? 0) === (int) $departmentId) {
            return true;
        }

        $ownName = mb_strtolower(trim((string) ($user->department?->name ?? '')));
        $rowName = mb_strtolower(trim((string) ($row['department'] ?? '')));

        if ($ownName !== '' && $rowName !== '') {
            $ownNorm = preg_replace('/[^\p{L}\p{N}]+/u', '', $ownName) ?? '';
            $rowNorm = preg_replace('/[^\p{L}\p{N}]+/u', '', $rowName) ?? '';

            if ($ownNorm !== '' && $rowNorm !== ''
                && ($ownNorm === $rowNorm
                    || str_contains($rowNorm, $ownNorm)
                    || str_contains($ownNorm, $rowNorm))) {
                return true;
            }
        }

        return ($row['department_id'] ?? null) === null && $rowName === '';
    }
}
