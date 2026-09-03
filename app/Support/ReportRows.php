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

        $out = [];

        foreach (array_values($rows) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $row['_index'] = $index;
            $row['department_id'] = null;

            $out[] = array_merge($row, $byRow[$index] ?? []);
        }

        return $out;
    }

    /**
     * Хамааралтай эрхтэй хэрэглэгчид зөвхөн өөрийн хэлтсийн мөрийг харуулна.
     *
     * Хэлтэс сонгоогүй мөр нь бүх хүнд харагдана — эзэнгүй ажил нуугдахгүй.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function visibleTo(array $rows, ?User $user): array
    {
        if (! ModuleAccess::scopeOwnOnly($user, 'reports')) {
            return $rows;
        }

        $departmentId = $user?->department_id;

        return array_values(array_filter(
            $rows,
            fn (array $row) => $row['department_id'] === null
                || (int) $row['department_id'] === (int) $departmentId,
        ));
    }
}
