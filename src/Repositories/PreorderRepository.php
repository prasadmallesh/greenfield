<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Customer pre-orders (sale_data rows flagged as customer_invoice / new_cust_invoice),
 * aligned with legacy GetPreOrdersList (sale_data branch; optional standard_data union when menu index 13 is on).
 */
final class PreorderRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPreorders(
        string $userType,
        int $userId,
        ?string $partyIdsCsv,
        bool $includeStandardPreorderUnion,
        ?string $fdtDmY,
        ?string $tdtDmY,
        ?int $filterPartyId,
        ?int $filterUserId,
        int $limit = 200,
    ): array {
        $params = [];
        $whereSale = "s.totamt > 0 AND s.customer_invoice = '1' AND s.new_cust_invoice = '1'";

        if ($userType !== 'A' && $partyIdsCsv !== null && $partyIdsCsv !== '') {
            $whereSale .= ' AND s.partyid IN (' . $this->quotedIntList($partyIdsCsv) . ')';
        } elseif ($userType !== 'A') {
            return [];
        }

        if ($fdtDmY !== null && $fdtDmY !== '' && $tdtDmY !== null && $tdtDmY !== '') {
            $whereSale .= " AND s.deliverydt >= STR_TO_DATE(:fdt,'%d/%m/%Y') AND s.deliverydt <= STR_TO_DATE(:tdt,'%d/%m/%Y')";
            $params[':fdt'] = $fdtDmY;
            $params[':tdt'] = $tdtDmY;
        }

        if ($filterPartyId !== null && $filterPartyId > 0) {
            $whereSale .= ' AND s.partyid = :fpid';
            $params[':fpid'] = $filterPartyId;
        }

        if ($filterUserId !== null && $filterUserId > 0 && $userType === 'A') {
            $whereSale .= ' AND p.uid = :fuid';
            $params[':fuid'] = $filterUserId;
        }

        $sqlSale = "SELECT s.sbid, s.totamt, s.paymode, p.partynm,
                DATE_FORMAT(s.sdate,'%d/%m/%Y') AS sdt, c.custname, '' AS invname,
                '' AS stid, DATE_FORMAT(s.deliverydt,'%d/%m/%Y') AS dvdt
            FROM sale_data s
            LEFT JOIN party_mast p ON s.partyid = p.partyid
            LEFT JOIN customer_mast c ON s.custid = c.custid
            WHERE {$whereSale}";

        $parts = [$sqlSale];
        if ($includeStandardPreorderUnion && $fdtDmY !== null && $fdtDmY !== '') {
            $dayExpr = "DATE_FORMAT(STR_TO_DATE(:fdt,'%d/%m/%Y'),'%w')";
            if (!isset($params[':fdt'])) {
                $params[':fdt'] = $fdtDmY;
            }
            $stdWhere = "s.totamt > 0 AND s.invday = {$dayExpr}";
            if ($userType !== 'A' && $partyIdsCsv !== null && $partyIdsCsv !== '') {
                $stdWhere .= ' AND s.partyid IN (' . $this->quotedIntList($partyIdsCsv) . ')';
            } elseif ($userType !== 'A') {
                $stdWhere .= ' AND 1=0';
            }
            $parts[] = "SELECT '' AS sbid, s.totamt, s.paymode, p.partynm, '' AS sdt, c.custname, 'STD' AS invname,
                s.sid AS stid, '' AS dvdt
                FROM standard_data s
                LEFT JOIN party_mast p ON s.partyid = p.partyid
                LEFT JOIN customer_mast c ON s.custid = c.custid
                WHERE {$stdWhere}";
        }

        $sql = '(' . implode(') UNION ALL (', $parts) . ') ORDER BY sbid DESC LIMIT ' . (int) $limit;
        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function markPreorderConvertedToInvoice(string $sbid): bool
    {
        $st = $this->pdo->prepare(
            "UPDATE sale_data SET customer_invoice = '0', new_cust_invoice = '0' WHERE sbid = :sbid"
        );

        return $st->execute([':sbid' => $sbid]);
    }

    private function quotedIntList(string $csv): string
    {
        $ids = array_filter(array_map('intval', explode(',', $csv)));

        return implode(',', $ids ?: [0]);
    }
}
