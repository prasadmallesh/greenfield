<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Read-only snapshots used by legacy dashboard / view-last-bill / qurbani / revert screens.
 */
final class LegacyDashboardRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Latest party_credit settlement block (legacy GetLastBillSettlementData).
     *
     * @return array{pdt:string,partynm:string,invno:string,payamt:string,paymode:string}|null
     */
    public function lastBillSettlementSnapshot(): ?array
    {
        try {
            $sql = 'SELECT DATE_FORMAT(pc.paydt, \'%d/%m/%Y\') AS pdt, COALESCE(p.partynm, \'\') AS partynm,
                COALESCE((SELECT sp.sbid FROM sale_payment_data sp WHERE sp.pcid = pc.pcid ORDER BY sp.payid ASC LIMIT 1), \'\') AS invno,
                COALESCE((SELECT SUM(sp2.paidamt) FROM sale_payment_data sp2 WHERE sp2.pcid = pc.pcid), 0) AS payamt,
                COALESCE(pc.paymode, \'\') AS paymode
                FROM party_credit_data pc
                LEFT JOIN party_mast p ON p.partyid = pc.partyid
                ORDER BY pc.pcid DESC LIMIT 1';
            $row = $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                return null;
            }

            return [
                'pdt' => (string) ($row['pdt'] ?? ''),
                'partynm' => (string) ($row['partynm'] ?? ''),
                'invno' => (string) ($row['invno'] ?? ''),
                'payamt' => (string) ($row['payamt'] ?? ''),
                'paymode' => (string) ($row['paymode'] ?? ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{pdt:string,last_party_name:string}|null
     */
    public function lastInvoiceEmailSnapshot(): ?array
    {
        try {
            $row = $this->pdo->query(
                'SELECT DATE_FORMAT(last_date, \'%d/%m/%Y\') AS pdt, COALESCE(last_party_name, \'\') AS last_party_name FROM last_invoice_email_data LIMIT 1'
            )->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                return null;
            }

            return [
                'pdt' => (string) ($row['pdt'] ?? ''),
                'last_party_name' => (string) ($row['last_party_name'] ?? ''),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listQurbaniRows(?string $dtype, ?string $location, int $limit = 500): array
    {
        try {
            $sql = 'SELECT qid, fname, lname, email, phno, address, itemlist,
                DATE_FORMAT(edate, \'%d/%m/%Y\') AS edt,
                DATE_FORMAT(dpdt, \'%d/%m/%Y\') AS ddt,
                dtype, location, order_status
                FROM qurbani_data WHERE 1=1';
            $params = [];
            if ($dtype !== null && $dtype !== '') {
                $sql .= ' AND dtype = :d';
                $params[':d'] = $dtype;
            }
            if ($location !== null && $location !== '') {
                $sql .= ' AND location = :l';
                $params[':l'] = $location;
            }
            $sql .= ' ORDER BY edate DESC LIMIT ' . max(1, min(2000, $limit));
            $st = $this->pdo->prepare($sql);
            $st->execute($params);

            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array{partynm:string,sbid:string,paydt:string,paidamt:float,invamt:float,payid:int}>
     */
    public function searchRevertPayments(string $fdt, string $tdt, int $partyId, int $offset, int $limit): array
    {
        $sql = 'SELECT p.partynm, s.sbid, DATE_FORMAT(sp.paydate, \'%d/%m/%Y\') AS paydt, sp.paidamt, s.totamt AS invamt, sp.payid
            FROM sale_data s
            INNER JOIN sale_payment_data sp ON s.sbid = sp.sbid
            LEFT JOIN party_mast p ON s.partyid = p.partyid
            WHERE sp.paidamt > 0';
        $params = [];
        if ($fdt !== '' && $tdt !== '') {
            $sql .= ' AND sp.paydate >= STR_TO_DATE(:fdt, \'%d/%m/%Y\') AND sp.paydate <= STR_TO_DATE(:tdt, \'%d/%m/%Y\')';
            $params[':fdt'] = $fdt;
            $params[':tdt'] = $tdt;
        }
        if ($partyId > 0) {
            $sql .= ' AND sp.partyid = :pid';
            $params[':pid'] = $partyId;
        }
        $sql .= ' ORDER BY sp.paydate DESC, sp.sbid DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[] = [
                'partynm' => (string) ($r['partynm'] ?? ''),
                'sbid' => (string) ($r['sbid'] ?? ''),
                'paydt' => (string) ($r['paydt'] ?? ''),
                'paidamt' => (float) ($r['paidamt'] ?? 0),
                'invamt' => (float) ($r['invamt'] ?? 0),
                'payid' => (int) ($r['payid'] ?? 0),
            ];
        }

        return $out;
    }

    public function countRevertPayments(string $fdt, string $tdt, int $partyId): int
    {
        $sql = 'SELECT COUNT(*) FROM sale_data s
            INNER JOIN sale_payment_data sp ON s.sbid = sp.sbid
            WHERE sp.paidamt > 0';
        $params = [];
        if ($fdt !== '' && $tdt !== '') {
            $sql .= ' AND sp.paydate >= STR_TO_DATE(:fdt, \'%d/%m/%Y\') AND sp.paydate <= STR_TO_DATE(:tdt, \'%d/%m/%Y\')';
            $params[':fdt'] = $fdt;
            $params[':tdt'] = $tdt;
        }
        if ($partyId > 0) {
            $sql .= ' AND sp.partyid = :pid';
            $params[':pid'] = $partyId;
        }
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return (int) $st->fetchColumn();
    }
}
