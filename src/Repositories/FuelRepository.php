<?php

declare(strict_types=1);

namespace App\Repositories;

final class FuelRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeFuelRows(): array
    {
        $st = $this->pdo->query("SELECT * FROM fuel_master WHERE is_active = '1'");

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
