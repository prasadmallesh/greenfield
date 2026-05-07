<?php

declare(strict_types=1);

namespace App\Repositories;

final class TaxMasterRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(int $limit = 200): array
    {
        $st = $this->pdo->query('SELECT * FROM tax_mast ORDER BY order_no ASC, taxid ASC LIMIT ' . $limit);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM tax_mast WHERE taxid = :id LIMIT 1');
        $st->execute([':id' => $id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        return $r ?: null;
    }

    public function insert(string $taxname, string $taxval, string $orderNo): void
    {
        $st = $this->pdo->prepare('INSERT INTO tax_mast (taxname, taxval, order_no) VALUES (:nm, :v, :o)');
        $st->execute([':nm' => $taxname, ':v' => $taxval, ':o' => $orderNo]);
    }

    public function update(int $id, string $taxname, string $taxval, string $orderNo): void
    {
        $st = $this->pdo->prepare('UPDATE tax_mast SET taxname = :nm, taxval = :v, order_no = :o WHERE taxid = :id');
        $st->execute([':nm' => $taxname, ':v' => $taxval, ':o' => $orderNo, ':id' => $id]);
    }

    public function delete(int $id): bool
    {
        $st = $this->pdo->prepare('DELETE FROM tax_mast WHERE taxid = :id');

        return $st->execute([':id' => $id]);
    }
}
