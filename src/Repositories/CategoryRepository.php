<?php

declare(strict_types=1);

namespace App\Repositories;

final class CategoryRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categoriesForParty(int $partyId): array
    {
        $sql = 'SELECT c.* FROM party_product_price pp
            LEFT JOIN product_mast p ON pp.pid = p.pid
            LEFT JOIN category_mast c ON p.catid = c.catid
            WHERE pp.partyid = :pid AND c.catid > 0
            GROUP BY c.catid
            ORDER BY c.catname ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([':pid' => $partyId]);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function productsInCategoryForParty(int $catId, int $partyId): array
    {
        $sql = 'SELECT p.*, pp.price FROM product_mast p
            LEFT JOIN party_product_price pp ON pp.pid = p.pid AND pp.partyid = :pid
            WHERE p.is_active = \'1\' AND p.catid = :cid
            ORDER BY p.pname ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([':pid' => $partyId, ':cid' => $catId]);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function productTaxInclude(int $pid): int
    {
        $st = $this->pdo->prepare('SELECT tax_include FROM product_mast WHERE pid = :p LIMIT 1');
        $st->execute([':p' => $pid]);
        $v = $st->fetchColumn();

        return $v !== false ? (int) $v : 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCategoriesMaster(int $limit = 500): array
    {
        $st = $this->pdo->query('SELECT * FROM category_mast ORDER BY catname ASC LIMIT ' . $limit);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProductsMaster(int $limit = 500): array
    {
        $st = $this->pdo->query(
            'SELECT p.*, c.catname FROM product_mast p
            LEFT JOIN category_mast c ON p.catid = c.catid
            ORDER BY p.pname ASC LIMIT ' . $limit
        );

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
