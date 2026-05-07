<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Party / account master — mirrors legacy party_mast fields used in party.php + SetPartyData.
 */
final class PartyMasterRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForMaster(int $staffUid, string $userType, int $limit = 500): array
    {
        $sql = 'SELECT p.*, a.user_name FROM party_mast p
            LEFT JOIN admin_user a ON a.uid = p.uid
            WHERE 1=1';
        if ($userType === 'U') {
            $sql .= ' AND p.uid = :uid';
        }
        $sql .= ' ORDER BY p.uent_dt DESC LIMIT ' . (int) $limit;
        $st = $this->pdo->prepare($sql);
        if ($userType === 'U') {
            $st->execute([':uid' => $staffUid]);
        } else {
            $st->execute();
        }

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $partyId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM party_mast WHERE partyid = :id LIMIT 1');
        $st->execute([':id' => $partyId]);
        $r = $st->fetch(\PDO::FETCH_ASSOC);

        return $r ?: null;
    }

    public function existsPartyName(string $partynmUpper, ?int $exceptPartyId): bool
    {
        if ($exceptPartyId === null || $exceptPartyId <= 0) {
            $st = $this->pdo->prepare('SELECT 1 FROM party_mast WHERE partynm = :n LIMIT 1');
            $st->execute([':n' => $partynmUpper]);
        } else {
            $st = $this->pdo->prepare('SELECT 1 FROM party_mast WHERE partynm = :n AND partyid <> :id LIMIT 1');
            $st->execute([':n' => $partynmUpper, ':id' => $exceptPartyId]);
        }

        return (bool) $st->fetchColumn();
    }

    /**
     * Approximate “has invoice balance” for deactivation rule (legacy GetPaymentByPartyID > 0).
     */
    public function partyHasInvoiceBalance(int $partyId): bool
    {
        $st = $this->pdo->prepare('SELECT sbid, totamt FROM sale_data WHERE partyid = :p');
        $st->execute([':p' => $partyId]);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $sbid = (string) $row['sbid'];
            $tot = (float) $row['totamt'];
            $st2 = $this->pdo->prepare('SELECT COALESCE(SUM(paidamt),0) FROM sale_payment_data WHERE sbid = :s');
            $st2->execute([':s' => $sbid]);
            $paid = (float) $st2->fetchColumn();
            if ($tot - $paid > 0.009) {
                return true;
            }
        }

        return false;
    }

    public function applyPartyAccountNumber(int $partyId): void
    {
        $accno = 'GFM' . ($partyId + 10000);
        $st = $this->pdo->prepare('UPDATE party_mast SET party_accno = :a WHERE partyid = :id');
        $st->execute([':a' => $accno, ':id' => $partyId]);
    }

    /**
     * @param array<string, mixed> $d normalized POST-like keys matching legacy form names
     * @return int new party id
     */
    public function insert(array $d): int
    {
        $uid = (int) ($d['uid'] ?? 0);
        $sql = 'INSERT INTO party_mast (partynm, address, city, state, contactno, tinno, email, sper, pterms, deliveryins, abnno,
            is_active, email2, uid, hide_invoice, cpersonm, outstanding_limit_amt, ignore_pro_minprice)
            VALUES (:partynm, :address, :city, :state, :contactno, :tinno, :email, :sper, :pterms, :deliveryins, :abnno,
            :is_active, :email2, :uid, :hide_invoice, :cpersonm, :outlim, :ignmin)';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':partynm' => $d['partynm'],
            ':address' => $d['address'],
            ':city' => $d['city'],
            ':state' => $d['state'],
            ':contactno' => $d['contactno'],
            ':tinno' => $d['tinno'],
            ':email' => $d['email'],
            ':sper' => $d['sper'],
            ':pterms' => $d['pterms'],
            ':deliveryins' => $d['deliveryins'],
            ':abnno' => $d['abnno'],
            ':is_active' => $d['is_active'],
            ':email2' => $d['email2'],
            ':uid' => $uid > 0 ? $uid : 0,
            ':hide_invoice' => $d['hide_invoice'],
            ':cpersonm' => $d['cpersonm'],
            ':outlim' => $d['outstanding_limit_amt'],
            ':ignmin' => $d['ignore_pro_minprice'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $partyId, array $d): void
    {
        $uid = (int) ($d['uid'] ?? 0);
        $sql = 'UPDATE party_mast SET partynm = :partynm, address = :address, city = :city, state = :state, contactno = :contactno,
            tinno = :tinno, email = :email, sper = :sper, pterms = :pterms, deliveryins = :deliveryins, abnno = :abnno,
            is_active = :is_active, email2 = :email2, uid = :uid, hide_invoice = :hide_invoice, cpersonm = :cpersonm,
            outstanding_limit_amt = :outlim, ignore_pro_minprice = :ignmin WHERE partyid = :id';
        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':partynm' => $d['partynm'],
            ':address' => $d['address'],
            ':city' => $d['city'],
            ':state' => $d['state'],
            ':contactno' => $d['contactno'],
            ':tinno' => $d['tinno'],
            ':email' => $d['email'],
            ':sper' => $d['sper'],
            ':pterms' => $d['pterms'],
            ':deliveryins' => $d['deliveryins'],
            ':abnno' => $d['abnno'],
            ':is_active' => $d['is_active'],
            ':email2' => $d['email2'],
            ':uid' => $uid > 0 ? $uid : 0,
            ':hide_invoice' => $d['hide_invoice'],
            ':cpersonm' => $d['cpersonm'],
            ':outlim' => $d['outstanding_limit_amt'],
            ':ignmin' => $d['ignore_pro_minprice'],
            ':id' => $partyId,
        ]);
    }

    /**
     * Legacy DelPartyById: remove related sale rows then party (party row removed first in legacy; we delete children then party).
     */
    public function deleteWithSales(int $partyId): void
    {
        $this->pdo->beginTransaction();
        try {
            $st = $this->pdo->prepare('SELECT sbid FROM sale_data WHERE partyid = :p');
            $st->execute([':p' => $partyId]);
            $sbids = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            foreach ($sbids as $sbid) {
                $sbid = (string) $sbid;
                $this->pdo->prepare('DELETE FROM sale_payment_data WHERE sbid = :s')->execute([':s' => $sbid]);
                $this->pdo->prepare('DELETE FROM sale_product_data WHERE sbid = :s')->execute([':s' => $sbid]);
                $this->pdo->prepare('DELETE FROM sale_data WHERE sbid = :s')->execute([':s' => $sbid]);
            }
            $this->pdo->prepare('DELETE FROM party_mast WHERE partyid = :p')->execute([':p' => $partyId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
