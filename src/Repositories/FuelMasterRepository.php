<?php

declare(strict_types=1);

namespace App\Repositories;

final class FuelMasterRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(int $limit = 100): array
    {
        $st = $this->pdo->query('SELECT * FROM fuel_master ORDER BY fuel_id DESC LIMIT ' . $limit);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM fuel_master WHERE fuel_id = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        return $r ?: null;
    }

    public function insert(string $fuelName, float $fuelCost, int $isActive): void
    {
        $st = $this->pdo->prepare(
            'INSERT INTO fuel_master (fuel_name, fuel_cost, is_active) VALUES (:n, :c, :a)'
        );
        $st->execute([':n' => $fuelName, ':c' => $fuelCost, ':a' => $isActive]);
    }

    public function existsName(string $fuelName, ?int $exceptId): bool
    {
        if ($exceptId === null || $exceptId <= 0) {
            $st = $this->pdo->prepare('SELECT 1 FROM fuel_master WHERE fuel_name = :n LIMIT 1');
            $st->execute([':n' => $fuelName]);
        } else {
            $st = $this->pdo->prepare('SELECT 1 FROM fuel_master WHERE fuel_name = :n AND fuel_id <> :id LIMIT 1');
            $st->execute([':n' => $fuelName, ':id' => $exceptId]);
        }

        return (bool) $st->fetchColumn();
    }

    public function update(int $id, string $fuelName, float $fuelCost, int $isActive): void
    {
        $st = $this->pdo->prepare(
            'UPDATE fuel_master SET fuel_name = :n, fuel_cost = :c, is_active = :a WHERE fuel_id = :id'
        );
        $st->execute([':n' => $fuelName, ':c' => $fuelCost, ':a' => $isActive, ':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM fuel_master WHERE fuel_id = :id');

        return $st->execute([':id' => $id]);
    }
}
