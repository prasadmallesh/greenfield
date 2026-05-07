<?php

declare(strict_types=1);

namespace App\Repositories;

final class ProductMasterRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAll(int $limit = 500): array
    {
        $st = $this->pdo->query(
            'SELECT p.*, c.catname FROM product_mast p
            LEFT JOIN category_mast c ON p.catid = c.catid
            ORDER BY p.pname ASC LIMIT ' . $limit
        );

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByIdWithPurchase(int $id): ?array
    {
        $sql = 'SELECT pm.*, ppd.purchase_cost FROM product_mast AS pm
            LEFT JOIN product_purchase_data AS ppd ON pm.pid = ppd.pid AND ppd.status = 1
            WHERE pm.pid = :id LIMIT 1';
        $st = $this->pdo->prepare($sql);
        $st->execute([':id' => $id]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        return $r ?: null;
    }

    /**
     * @return array{pid:int}
     */
    public function insertProduct(
        int $catid,
        string $pname,
        string $description,
        int $taxInclude,
        string $punit,
        string $photo,
        float $mincost,
        int $isActive,
        float $purchaseCost,
    ): array {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare(
                'INSERT INTO product_mast (pname, description, tax_include, catid, photo, punit, mincost, is_active)
                VALUES (:pname, :desc, :tax, :cat, :photo, :punit, :minc, :act)'
            );
            $st->execute([
                ':pname' => $pname,
                ':desc' => $description,
                ':tax' => $taxInclude,
                ':cat' => $catid,
                ':photo' => $photo,
                ':punit' => $punit,
                ':minc' => $mincost,
                ':act' => $isActive,
            ]);
            $pid = (int) $this->pdo->lastInsertId();
            $today = date('Y-m-d');
            $st2 = $this->pdo->prepare(
                'INSERT INTO product_purchase_data (pid, purchase_cost, start_date) VALUES (:pid, :cost, :sd)'
            );
            $st2->execute([':pid' => $pid, ':cost' => $purchaseCost, ':sd' => $today]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return ['pid' => $pid];
    }

    public function updateProduct(
        int $pid,
        int $catid,
        string $pname,
        string $description,
        int $taxInclude,
        string $punit,
        ?string $newPhoto,
        float $mincost,
        int $isActive,
        float $purchaseCost,
    ): void {
        $today = date('Y-m-d');
        $this->pdo->beginTransaction();
        try {
            if ($newPhoto !== null && $newPhoto !== '') {
                $st = $this->pdo->prepare(
                    'UPDATE product_mast SET pname = :pname, description = :desc, tax_include = :tax,
                        catid = :cat, photo = :photo, punit = :punit, mincost = :minc, is_active = :act WHERE pid = :pid'
                );
                $st->execute([
                    ':pname' => $pname,
                    ':desc' => $description,
                    ':tax' => $taxInclude,
                    ':cat' => $catid,
                    ':photo' => $newPhoto,
                    ':punit' => $punit,
                    ':minc' => $mincost,
                    ':act' => $isActive,
                    ':pid' => $pid,
                ]);
            } else {
                $st = $this->pdo->prepare(
                    'UPDATE product_mast SET pname = :pname, description = :desc, tax_include = :tax,
                        catid = :cat, punit = :punit, mincost = :minc, is_active = :act WHERE pid = :pid'
                );
                $st->execute([
                    ':pname' => $pname,
                    ':desc' => $description,
                    ':tax' => $taxInclude,
                    ':cat' => $catid,
                    ':punit' => $punit,
                    ':minc' => $mincost,
                    ':act' => $isActive,
                    ':pid' => $pid,
                ]);
            }

            $stCur = $this->pdo->prepare(
                'SELECT purchase_cost, start_date FROM product_purchase_data WHERE pid = :pid AND status = 1 LIMIT 1'
            );
            $stCur->execute([':pid' => $pid]);
            $cur = $stCur->fetch(\PDO::FETCH_ASSOC);
            $needNew = $cur === false || abs((float) $cur['purchase_cost'] - $purchaseCost) > 0.0001;
            if ($needNew) {
                if ($cur && ($cur['start_date'] ?? '') === $today) {
                    $u = $this->pdo->prepare('UPDATE product_purchase_data SET purchase_cost = :c WHERE pid = :pid AND status = 1');
                    $u->execute([':c' => $purchaseCost, ':pid' => $pid]);
                } else {
                    $e = $this->pdo->prepare('UPDATE product_purchase_data SET end_date = :ed, status = 0 WHERE pid = :pid AND status = 1');
                    $e->execute([':ed' => $today, ':pid' => $pid]);
                    $ins = $this->pdo->prepare(
                        'INSERT INTO product_purchase_data (pid, purchase_cost, start_date) VALUES (:pid, :cost, :sd)'
                    );
                    $ins->execute([':pid' => $pid, ':cost' => $purchaseCost, ':sd' => $today]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $this->pdo->prepare('DELETE FROM product_purchase_data WHERE pid = :p')->execute([':p' => $id]);
        $st = $this->pdo->prepare('DELETE FROM product_mast WHERE pid = :p');

        return $st->execute([':p' => $id]);
    }
}
