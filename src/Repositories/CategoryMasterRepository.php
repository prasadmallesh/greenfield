<?php

declare(strict_types=1);

namespace App\Repositories;

final class CategoryMasterRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(int $limit = 500): array
    {
        $st = $this->pdo->query('SELECT * FROM category_mast ORDER BY catname ASC LIMIT ' . $limit);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM category_mast WHERE catid = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        return $r ?: null;
    }

    public function insert(string $catnameUpper): void
    {
        $st = $this->pdo->prepare('INSERT INTO category_mast (catname) VALUES (:n)');
        $st->execute([':n' => $catnameUpper]);
    }

    public function update(int $id, string $catnameUpper): void
    {
        $st = $this->pdo->prepare('UPDATE category_mast SET catname = :n WHERE catid = :id');
        $st->execute([':n' => $catnameUpper, ':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM category_mast WHERE catid = :id');

        return $st->execute([':id' => $id]);
    }

    public function existsNameOtherThan(string $nameUpper, ?int $exceptId): bool
    {
        if ($exceptId === null || $exceptId <= 0) {
            $st = $this->pdo->prepare('SELECT 1 FROM category_mast WHERE catname = :n LIMIT 1');
            $st->execute([':n' => $nameUpper]);
        } else {
            $st = $this->pdo->prepare('SELECT 1 FROM category_mast WHERE catname = :n AND catid <> :id LIMIT 1');
            $st->execute([':n' => $nameUpper, ':id' => $exceptId]);
        }

        return (bool) $st->fetchColumn();
    }
}
