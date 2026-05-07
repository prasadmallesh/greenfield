<?php

declare(strict_types=1);

namespace App\Repositories;

final class CustomerCartRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function clearCart(int $partyId): void
    {
        $st = $this->pdo->prepare('DELETE FROM tmp_customer_sale WHERE partyid = :p');
        $st->execute([':p' => $partyId]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cartLines(int $partyId): array
    {
        $sql = 'SELECT tp.*, p.pname FROM tmp_customer_sale tp
            LEFT JOIN product_mast p ON tp.pid = p.pid
            WHERE tp.partyid = :p ORDER BY tp.pid ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([':p' => $partyId]);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteLine(int $partyId, int $pid): bool
    {
        $st = $this->pdo->prepare('DELETE FROM tmp_customer_sale WHERE partyid = :p AND pid = :pid');

        return $st->execute([':p' => $partyId, ':pid' => $pid]);
    }

    public function insertLine(
        int $partyId,
        int $pid,
        float $qty,
        string $punit,
        float $rate,
        float $amt,
        float $taxamt,
        float $netAmt,
        string $payterm,
    ): bool {
        $sql = 'INSERT INTO tmp_customer_sale (pid, qty, punit, rate, amt, taxamt, net_amt, partyid, payterm)
            VALUES (:pid, :qty, :punit, :rate, :amt, :taxamt, :net, :pty, :pt)';
        $st = $this->pdo->prepare($sql);

        return $st->execute([
            ':pid' => $pid,
            ':qty' => $qty,
            ':punit' => $punit,
            ':rate' => $rate,
            ':amt' => $amt,
            ':taxamt' => $taxamt,
            ':net' => $netAmt,
            ':pty' => $partyId,
            ':pt' => $payterm,
        ]);
    }

    public function partyPayterm(int $partyId): string
    {
        $st = $this->pdo->prepare('SELECT pterms FROM party_mast WHERE partyid = :p LIMIT 1');
        $st->execute([':p' => $partyId]);
        $v = $st->fetchColumn();

        return $v !== false ? (string) $v : '';
    }
}
