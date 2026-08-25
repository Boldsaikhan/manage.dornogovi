<?php

namespace App\Services\Ai\Tools;

use App\Models\Leave;
use App\Models\User;
use Carbon\Carbon;

class LeaveTools
{
    public function mine(User $user, array $args = []): array
    {
        $items = Leave::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Leave $l) => $this->map($l))
            ->all();

        return ['items' => $items, 'source' => 'leaves'];
    }

    public function search(User $user, array $args = []): array
    {
        $status = $args['status'] ?? null;
        $month = isset($args['month']) ? (int) $args['month'] : null;
        $year = isset($args['year']) ? (int) $args['year'] : (int) now()->year;

        $query = Leave::query()->with(['user:id,name', 'department:id,name'])->orderByDesc('id');
        if ($status) {
            $query->where('status', $status);
        }
        if ($month) {
            $query->whereMonth('start_date', $month)->whereYear('start_date', $year);
        } elseif (! empty($args['this_month'])) {
            $query->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year);
        }

        $count = (clone $query)->count();

        return [
            'count' => $count,
            'items' => $query->limit(30)->get()->map(fn (Leave $l) => $this->map($l))->all(),
            'source' => 'leaves',
        ];
    }

    /**
     * Зөвхөн draft бэлдэнэ — DB-д бичихгүй.
     */
    public function prepareCreate(User $user, array $args = []): array
    {
        $start = $args['start_date'] ?? null;
        $end = $args['end_date'] ?? null;
        $type = $args['type'] ?? 'chuluu';
        $reason = $args['reason'] ?? null;

        $days = null;
        if ($start && $end) {
            try {
                $days = Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
            } catch (\Throwable) {
                $days = null;
            }
        }

        return [
            'requires_confirmation' => true,
            'action' => 'CREATE_LEAVE_REQUEST',
            'draft' => [
                'type' => $type,
                'start_date' => $start,
                'end_date' => $end,
                'days' => $days,
                'reason' => $reason,
                'user_id' => $user->id,
                'department_id' => $user->department_id,
            ],
            'message' => 'Чөлөөний хүсэлтийн төслийг бэлдлээ. Баталгаажуулсны дараа л бүртгэнэ.',
        ];
    }

    private function map(Leave $l): array
    {
        return [
            'id' => $l->id,
            'user' => $l->user?->name,
            'department' => $l->department?->name,
            'type' => $l->type,
            'start_date' => optional($l->start_date)?->format('Y-m-d'),
            'end_date' => optional($l->end_date)?->format('Y-m-d'),
            'days' => $l->days,
            'status' => $l->status,
            'reason' => $l->reason,
        ];
    }
}
