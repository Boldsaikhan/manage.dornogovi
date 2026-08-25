<?php

namespace App\Services\Ai;

use App\Models\AiDailyUsage;
use App\Models\User;
use Carbon\Carbon;

class AiRateLimiter
{
    public function __construct(private AiSettings $settings) {}

    public function remaining(User $user): ?int
    {
        $limit = $this->settings->dailyQuestionLimit();
        if ($limit <= 0) {
            return null; // unlimited
        }

        return max(0, $limit - $this->usedToday($user));
    }

    public function usedToday(User $user): int
    {
        return (int) AiDailyUsage::query()
            ->where('user_id', $user->id)
            ->whereDate('usage_date', Carbon::today())
            ->value('questions');
    }

    public function canAsk(User $user): bool
    {
        $remaining = $this->remaining($user);

        return $remaining === null || $remaining > 0;
    }

    public function hit(User $user): void
    {
        $limit = $this->settings->dailyQuestionLimit();
        if ($limit <= 0) {
            return;
        }

        $row = AiDailyUsage::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'usage_date' => Carbon::today()->toDateString(),
            ],
            ['questions' => 0]
        );

        $row->increment('questions');
    }
}
