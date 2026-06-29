<?php

namespace App\Services;

use App\Repositories\DailyCheckinRepository;

class DailyCheckinService
{
    private DailyCheckinRepository $checkins;

    public function __construct()
    {
        $this->checkins = new DailyCheckinRepository();
    }

    public function hasTodayCheckin(int $userId): bool
    {
        return $this->checkins->findForDate($userId, date('Y-m-d')) !== null;
    }

    public function createToday(int $userId, string $status, string $note = ''): int
    {
        return $this->checkins->create($userId, date('Y-m-d'), $status, $note);
    }
}
