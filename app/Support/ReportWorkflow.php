<?php

namespace App\Support;

use App\Models\ReportRowApproval;
use App\Models\ReportSchedule;
use App\Models\User;

/**
 * Тайлангийн мөрийн урсгал: хэлтэс бөглөнө → хэлтсийн дарга
 * баталгаажуулна → хяналт-шинжилгээ үнэлнэ.
 */
class ReportWorkflow
{
    /** Энэ загвар баталгаажуулалттай эсэх. */
    public static function needsApproval(?array $template): bool
    {
        return (bool) ($template['approval'] ?? false);
    }

    /** Гэрээ ирүүлэх хугацаа тавих эсэх. */
    public static function hasSchedule(?array $template): bool
    {
        return (bool) ($template['schedule'] ?? false);
    }

    /**
     * Зөвхөн хяналт-шинжилгээний хүн бөглөх багана.
     *
     * @return list<string>
     */
    public static function monitorColumns(?array $template): array
    {
        $columns = $template['monitor_columns'] ?? [];

        return is_array($columns) ? array_values($columns) : [];
    }

    /**
     * Томъёоны тохиргоо: аль багана нь томъёо, зорилт, гүйцэтгэл, хувь вэ.
     *
     * @return array{column: string, target: string, actual: string, percent: string}|null
     */
    public static function formulaMap(?array $template): ?array
    {
        $map = $template['formula'] ?? null;

        if (! is_array($map)) {
            return null;
        }

        foreach (['column', 'target', 'actual', 'percent'] as $part) {
            if (! isset($map[$part])) {
                return null;
            }
        }

        return $map;
    }

    /**
     * Мөрийг баталгаажуулах эрхтэй эсэх.
     *
     * Хэлтсийн дарга өөрийн хэлтсийн мөрийг, админ бүгдийг.
     */
    public static function canApprove(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return (bool) $user->is_admin
            || (bool) $user->is_department_head
            || ModuleAccess::canManage($user, 'reports');
    }

    /**
     * Хяналт-шинжилгээний үнэлгээ өгөх эрхтэй эсэх.
     *
     * «Удирдах (бүгд)» эрхтэй хүн — хяналт-шинжилгээний албан хаагч.
     */
    public static function canMonitor(?User $user): bool
    {
        return ModuleAccess::canManage($user, 'reports');
    }

    /**
     * Баталгаажсан мөрүүдийн индекс.
     *
     * @return array<int, array{at: string, by: string|null}>
     */
    public static function approvals(string $reportKey): array
    {
        return ReportRowApproval::query()
            ->with('approver:id,name')
            ->where('report_key', $reportKey)
            ->get()
            ->mapWithKeys(fn (ReportRowApproval $row) => [
                $row->row_index => [
                    'at' => $row->approved_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                    'by' => $row->approver?->name,
                ],
            ])
            ->all();
    }

    /**
     * Гэрээ ирүүлэх хугацаа.
     *
     * @return array{due_on: string|null, note: string|null, overdue: bool, days_left: int|null}
     */
    public static function schedule(string $reportKey): array
    {
        $row = ReportSchedule::query()->where('report_key', $reportKey)->first();
        $due = $row?->due_on;

        return [
            'due_on' => $due?->format('Y-m-d'),
            'note' => $row?->note,
            'overdue' => $due !== null && $due->isPast() && ! $due->isToday(),
            'days_left' => $due === null ? null : (int) now()->startOfDay()->diffInDays($due->startOfDay(), false),
        ];
    }
}
