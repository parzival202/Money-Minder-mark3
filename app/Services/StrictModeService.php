<?php

namespace App\Services;

use App\Repositories\MetaRepository;

class StrictModeService
{
    private MetaRepository $meta;

    public function __construct()
    {
        $this->meta = new MetaRepository();
    }

    public function isEnabled(int $userId): bool
    {
        return $this->meta->getForUser('strict_mode', $userId, '0') === '1';
    }

    public function setEnabled(int $userId, bool $enabled): void
    {
        $this->meta->setForUser('strict_mode', $enabled ? '1' : '0', $userId);
    }
}
