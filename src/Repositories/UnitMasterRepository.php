<?php

declare(strict_types=1);

namespace App\Repositories;

final class UnitMasterRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(int $limit = 200): array
    {
        $st = $this->pdo->query('SELECT * FROM unit_mast ORDER BY utid ASC LIMIT ' . $limit);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM unit_mast WHERE utid = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        return $r ?: null;
    }

    public function insert(string $unitnmUpper): void
    {
        $st = $this->pdo->prepare('INSERT INTO unit_mast (unitnm) VALUES (:u)');
        $st->execute([':u' => $unitnmUpper]);
    }

    public function update(int $id, string $unitnmUpper): void
    {
        $st = $this->pdo->prepare('UPDATE unit_mast SET unitnm = :u WHERE utid = :id');
        $st->execute([':u' => $unitnmUpper, ':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM unit_mast WHERE utid = :id');

        return $st->execute([':id' => $id]);
    }
}
