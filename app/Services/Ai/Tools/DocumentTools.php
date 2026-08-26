<?php

namespace App\Services\Ai\Tools;

use App\Models\Archive;
use App\Models\Contract;
use App\Models\Decree;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\Regulation;
use App\Models\Report;
use App\Models\TravelAssignment;
use App\Models\User;
use Carbon\Carbon;

class DocumentTools
{
    public function searchDecrees(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $year = isset($args['year']) ? (int) $args['year'] : null;
        $days = isset($args['days']) ? (int) $args['days'] : null;
        $kind = trim((string) ($args['kind'] ?? ''));
        $category = trim((string) ($args['category'] ?? ''));

        $query = Decree::query()
            ->where('kind', '!=', 'blank')
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->limit(20);

        if ($category !== '' && $category !== 'blank') {
            $query->where('category', $category);
        }

        if ($kind !== '') {
            if (str_ends_with($kind, '_')) {
                $query->where('kind', 'like', $kind.'%');
            } else {
                $query->where('kind', $kind);
            }
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('number', 'like', "%{$q}%")
                    ->orWhere('blank_number', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%")
                    ->orWhere('person_name', 'like', "%{$q}%")
                    ->orWhere('attachment_name', 'like', "%{$q}%")
                    ->orWhere('num_zahiramj', 'like', "%{$q}%")
                    ->orWhere('num_tushaal', 'like', "%{$q}%");
            });
        }

        if ($year) {
            $query->where(function ($w) use ($year) {
                $w->whereYear('issued_on', $year)
                    ->orWhere(function ($inner) use ($year) {
                        $inner->whereNull('issued_on')->whereYear('created_at', $year);
                    });
            });
        }

        if ($days) {
            $sinceDate = Carbon::now()->subDays($days)->toDateString();
            $sinceAt = Carbon::now()->subDays($days);
            $query->where(function ($w) use ($sinceDate, $sinceAt) {
                $w->where('issued_on', '>=', $sinceDate)
                    ->orWhere(function ($inner) use ($sinceAt) {
                        $inner->whereNull('issued_on')->where('created_at', '>=', $sinceAt);
                    });
            });
        }

        return [
            'items' => $query->get()->map(fn (Decree $d) => [
                'id' => $d->id,
                'kind' => $d->kindLabel(),
                'kind_key' => $d->kind,
                'number' => $d->number,
                'title' => $d->title ?: ($d->person_name ?: '—'),
                'person_name' => $d->person_name,
                'issued_on' => optional($d->issued_on)?->format('Y-m-d')
                    ?? optional($d->created_at)?->format('Y-m-d'),
                'route' => 'decrees.index',
                'params' => ['tab' => $d->kind ?: 'niit'],
                'href' => route('decrees.index', ['tab' => $d->kind ?: 'niit']),
            ])->all(),
            'source' => 'decrees',
        ];
    }

    public function searchRegulations(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $query = Regulation::query()->orderByDesc('id')->limit(15);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%");
            });
        }

        return [
            'items' => $query->get(['id', 'title', 'category', 'published_at'])->map(fn (Regulation $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'category' => $r->category,
                'route' => 'regulations.index',
                'href' => route('regulations.index'),
            ])->all(),
            'source' => 'regulations',
        ];
    }

    public function searchArchives(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $query = Archive::query()->orderByDesc('id')->limit(15);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%");
            });
        }

        return [
            'items' => $query->get(['id', 'title', 'category', 'year'])->map(fn (Archive $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'category' => $a->category,
                'year' => $a->year,
                'route' => 'archives.index',
                'href' => route('archives.index'),
            ])->all(),
            'source' => 'archives',
        ];
    }

    public function searchContracts(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $query = Contract::query()->orderByDesc('issued_on')->limit(15);
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('number', 'like', "%{$q}%")
                    ->orWhere('counterparty', 'like', "%{$q}%");
            });
        }

        return [
            'items' => $query->get(['id', 'number', 'title', 'counterparty', 'issued_on'])->map(fn (Contract $c) => [
                'id' => $c->id,
                'number' => $c->number,
                'title' => $c->title,
                'counterparty' => $c->counterparty,
                'issued_on' => optional($c->issued_on)?->format('Y-m-d'),
                'route' => 'contracts.index',
                'href' => route('contracts.index'),
            ])->all(),
            'source' => 'contracts',
        ];
    }

    public function searchPlans(User $user, array $args = []): array
    {
        $query = Plan::query()->orderByDesc('id')->limit(15);
        if (! empty($args['active'])) {
            $query->where('status', 'active');
        }

        return [
            'items' => $query->get(['id', 'title', 'year', 'period', 'status'])->map(fn (Plan $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'year' => $p->year,
                'period' => $p->period,
                'status' => $p->status,
                'route' => 'plans.index',
                'href' => route('plans.index'),
            ])->all(),
            'source' => 'plans',
        ];
    }

    public function searchMeetings(User $user, array $args = []): array
    {
        $query = Meeting::query()->orderByDesc('held_at')->limit(15);
        if (! empty($args['today'])) {
            $query->whereDate('held_at', Carbon::today());
        }

        return [
            'items' => $query->get(['id', 'title', 'held_at', 'status'])->map(fn (Meeting $m) => [
                'id' => $m->id,
                'title' => $m->title,
                'held_at' => optional($m->held_at)?->format('Y-m-d H:i'),
                'status' => $m->status,
                'route' => 'meetings.index',
                'href' => route('meetings.index'),
            ])->all(),
            'source' => 'meetings',
        ];
    }

    public function searchReports(User $user, array $args = []): array
    {
        $q = trim((string) ($args['q'] ?? ''));
        $query = Report::query()->orderByDesc('id')->limit(15);
        if ($q !== '') {
            $query->where('title', 'like', "%{$q}%");
        }

        return [
            'items' => $query->get(['id', 'title', 'period'])->map(fn (Report $r) => [
                'id' => $r->id,
                'title' => $r->title,
                'period' => $r->period,
                'route' => 'reports.index',
                'href' => route('reports.index'),
            ])->all(),
            'source' => 'reports',
        ];
    }

    public function myTrips(User $user, array $args = []): array
    {
        $query = TravelAssignment::query()->where('user_id', $user->id)->orderByDesc('start_date')->limit(20);
        if (! empty($args['this_month'])) {
            $query->whereMonth('start_date', now()->month)->whereYear('start_date', now()->year);
        }

        return [
            'items' => $query->get()->map(fn (TravelAssignment $t) => [
                'id' => $t->id,
                'destination' => $t->destination,
                'purpose' => $t->purpose,
                'start_date' => optional($t->start_date)?->format('Y-m-d'),
                'end_date' => optional($t->end_date)?->format('Y-m-d'),
                'status' => $t->status,
                'route' => 'assignments.index',
                'href' => route('assignments.index'),
            ])->all(),
            'source' => 'assignments',
        ];
    }
}
