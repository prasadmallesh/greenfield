<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Staff invoice / previous-balance settlement (parity with legacy sale-bill-settlement.php).
 */
final class BillSettlementService
{
    public function __construct(private \PDO $pdo)
    {
    }

    /** @return array<string, string> */
    public static function settlementPayModeOptions(): array
    {
        return [
            'CS' => 'CASH',
            'CQ' => 'CHEQUE',
            'CC' => 'CREDIT CARD',
            'BT' => 'BANK TRANSFER',
            'N' => 'CREDIT AMOUNT',
        ];
    }

    /**
     * @return list<array{partyid:int,custid:int,sbid:string,sid:int,pdt:string,invtotamt:float,invpayamt:float,invcramt:float}>
     */
    public function getOutstandingInvoiceRows(
        string $fdt,
        string $tdt,
        int $pid,
        int $cid,
        string $sv,
        ?string $partyIdsCsv,
    ): array {
        $sv = strtoupper(trim($sv));
        $conds = ["s.ccredit = '0'", "s.new_cust_invoice = '0'"];
        $params = [];

        if ($fdt !== '' && $tdt !== '') {
            $conds[] = "s.sdate >= STR_TO_DATE(:fdt, '%d/%m/%Y') AND s.sdate <= STR_TO_DATE(:tdt, '%d/%m/%Y')";
            $params['fdt'] = $fdt;
            $params['tdt'] = $tdt;
        }
        if ($pid > 0) {
            $conds[] = 's.partyid = :pid';
            $params['pid'] = $pid;
        }
        if ($cid > 0) {
            $conds[] = 's.custid = :cid';
            $params['cid'] = $cid;
        }
        if ($sv !== '') {
            $conds[] = 's.sbid = :sv';
            $params['sv'] = $sv;
        }
        if ($partyIdsCsv !== null && $partyIdsCsv !== '') {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $partyIdsCsv)))));
            if ($ids !== []) {
                $in = [];
                foreach ($ids as $i => $id) {
                    $k = 'g' . $i;
                    $in[] = ':' . $k;
                    $params[$k] = $id;
                }
                $conds[] = 's.partyid IN (' . implode(',', $in) . ')';
            }
        }

        $where = implode(' AND ', $conds);
        $payConds = ['1=1'];
        if ($pid > 0) {
            $payConds[] = 'sp.partyid = :pid';
            $params['pid'] = $pid;
        }
        if ($cid > 0) {
            $payConds[] = 'sp.custid = :cid';
            $params['cid'] = $cid;
        }
        if ($sv !== '') {
            $payConds[] = 'sp.sbid = :sv';
            $params['sv'] = $sv;
        }
        if ($partyIdsCsv !== null && $partyIdsCsv !== '') {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $partyIdsCsv)))));
            if ($ids !== []) {
                $in = [];
                foreach ($ids as $i => $id) {
                    $k = 'h' . $i;
                    $in[] = ':' . $k;
                    $params[$k] = $id;
                }
                $payConds[] = 'sp.partyid IN (' . implode(',', $in) . ')';
            }
        }

        $payWhere = implode(' AND ', $payConds);
        $sql = "SELECT x.partyid, x.custid, x.sbid, x.sid, DATE_FORMAT(x.sdate, '%d/%m/%Y') AS pdt,
                x.invtotamt, COALESCE(p.tot_paid, 0) AS invpayamt
            FROM (
                SELECT s.partyid, s.custid, s.sbid, MIN(s.sid) AS sid, MIN(s.sdate) AS sdate, SUM(s.totamt) AS invtotamt
                FROM sale_data s
                WHERE {$where}
                GROUP BY s.partyid, s.custid, s.sbid
            ) x
            LEFT JOIN (
                SELECT sp.sbid, SUM(sp.paidamt) AS tot_paid
                FROM sale_payment_data sp
                WHERE {$payWhere}
                GROUP BY sp.sbid
            ) p ON p.sbid = x.sbid
            WHERE ROUND(x.invtotamt, 2) <> ROUND(COALESCE(p.tot_paid, 0), 2)
            ORDER BY x.sdate ASC, x.sbid ASC";

        $st = $this->pdo->prepare($sql);
        $st->execute($params);

        $out = [];
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[] = [
                'partyid' => (int) $row['partyid'],
                'custid' => (int) $row['custid'],
                'sbid' => (string) $row['sbid'],
                'sid' => (int) $row['sid'],
                'pdt' => (string) $row['pdt'],
                'invtotamt' => (float) $row['invtotamt'],
                'invpayamt' => (float) $row['invpayamt'],
                'invcramt' => 0.0,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPartyCreditInvoices(int $partyId): array
    {
        $sql = 'SELECT s.sbid, s.totamt, COALESCE(ap.total_cramt, 0) AS cramt
            FROM sale_data s
            LEFT JOIN (
                SELECT pcsbid, SUM(cramt) AS total_cramt FROM sale_payment_data GROUP BY pcsbid
            ) ap ON s.sbid = ap.pcsbid
            WHERE s.partyid = :p AND s.ccredit = \'1\'
            HAVING ROUND(s.totamt, 2) <> ROUND(COALESCE(ap.total_cramt, 0), 2)
            ORDER BY s.sbid ASC';
        $st = $this->pdo->prepare($sql);
        $st->execute([':p' => $partyId]);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Remaining credit on a party credit invoice (legacy get_party_credit). */
    public function getRemainingCreditOnInvoice(string $sbid): float
    {
        $sbid = strtoupper(trim($sbid));
        $sql = 'SELECT s.totamt,
            COALESCE(SUM(CASE WHEN sp.pcsbid IS NOT NULL AND TRIM(sp.pcsbid) <> \'\' THEN sp.paidamt ELSE 0 END), 0) AS used_cramt
            FROM sale_data s
            LEFT JOIN sale_payment_data sp ON (sp.sbid = s.sbid OR sp.pcsbid = s.sbid)
            WHERE s.sbid = :s
            GROUP BY s.sbid, s.totamt';
        $st = $this->pdo->prepare($sql);
        $st->execute([':s' => $sbid]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return 0.0;
        }
        $tot = (float) ($row['totamt'] ?? 0);
        $used = (float) ($row['used_cramt'] ?? 0);

        return max(0.0, round($tot - $used, 2));
    }

    /**
     * One synthetic “previous balance” row for the grid, or null.
     *
     * @return array{bid:int|string,cdt:string,baltotamt:float,balpayamt:float}|null
     */
    public function getOpeningBalanceRow(string $ctype, int $pid, int $cid): ?array
    {
        if ($ctype === 'P' && $pid > 0) {
            $sql = "SELECT MIN(ob.bid) AS bid,
                SUM(ob.bamt) AS baltotamt,
                MIN(DATE_FORMAT(ob.cdate, '%d/%m/%Y')) AS cdt,
                COALESCE(SUM(bp.paidamt), 0) AS balpayamt
                FROM opening_balance_amt ob
                LEFT JOIN balance_payment_data bp ON bp.bid = ob.bid
                WHERE ob.ctype = 'P' AND ob.partyid = :pid
                GROUP BY ob.partyid";
            $st = $this->pdo->prepare($sql);
            $st->execute([':pid' => $pid]);
        } elseif ($ctype === 'C' && $cid > 0) {
            $sql = "SELECT MIN(ob.bid) AS bid,
                SUM(ob.bamt) AS baltotamt,
                MIN(DATE_FORMAT(ob.cdate, '%d/%m/%Y')) AS cdt,
                COALESCE(SUM(bp.paidamt), 0) AS balpayamt
                FROM opening_balance_amt ob
                LEFT JOIN balance_payment_data bp ON bp.bid = ob.bid
                WHERE ob.ctype = 'C' AND ob.custid = :cid
                GROUP BY ob.custid";
            $st = $this->pdo->prepare($sql);
            $st->execute([':cid' => $cid]);
        } else {
            return null;
        }

        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if ($row === false || (float) ($row['baltotamt'] ?? 0) <= 0) {
            return null;
        }

        return [
            'bid' => $row['bid'] ?? 0,
            'cdt' => (string) ($row['cdt'] ?? ''),
            'baltotamt' => (float) ($row['baltotamt'] ?? 0),
            'balpayamt' => (float) ($row['balpayamt'] ?? 0),
        ];
    }

    /**
     * @return array{msg:string, ctype:string, partyid:int, custid:int}
     */
    public function saveFromPost(array $arr): array
    {
        $msg = 'Nothing to save.';
        $partyid = 0;
        $custid = 0;
        $btnval = (string) ($arr['btnsave'] ?? '');
        $ctype = '';
        if (!empty($arr['ctype']) && is_array($arr['ctype'])) {
            $ctype = (string) $arr['ctype'][0];
        }
        $chk = $arr['chk'] ?? [];
        if (!is_array($chk)) {
            $chk = $chk !== null && $chk !== '' ? [$chk] : [];
        }
        if ($btnval !== 'Save' || $chk === []) {
            return ['msg' => $msg, 'ctype' => $ctype, 'partyid' => $partyid, 'custid' => $custid];
        }

        $payarr = is_array($arr['payamt'] ?? null) ? $arr['payamt'] : [];
        $tpayamt = is_array($arr['tpayamt'] ?? null) ? $arr['tpayamt'] : [];
        $paycramt = is_array($arr['paycramt'] ?? null) ? $arr['paycramt'] : [];
        $invdt = (string) ($arr['invdt'] ?? '');
        $paytype = (string) ($arr['paytype'] ?? '');
        $modearr = is_array($arr['pbmode'] ?? null) ? $arr['pbmode'] : [];
        $party_cr_inv = (string) ($arr['partycr'] ?? '');
        $prev_balamt = (string) ($arr['hd_total_balamt'] ?? '0');
        $party_cr_amt = (string) ($arr['hd_credit_amt'] ?? '0');

        if ($ctype === 'P') {
            $partyid = (int) (!empty($arr['pname']) ? $arr['pname'] : ($arr['hd_partynm'] ?? 0));
        } else {
            $custid = (int) (!empty($arr['custname']) ? $arr['custname'] : ($arr['hd_custnm'] ?? 0));
        }

        $this->pdo->beginTransaction();
        try {
            $pcIns = $this->pdo->prepare(
                'INSERT INTO party_credit_data (partyid, custid, amt_cr, paymode, paydt, sbid)
                 VALUES (:partyid, :custid, :amt_cr, :paymode, STR_TO_DATE(:paydt, \'%d/%m/%Y\'), :sbid)'
            );
            $pcIns->execute([
                ':partyid' => $partyid,
                ':custid' => $custid,
                ':amt_cr' => (float) $party_cr_amt,
                ':paymode' => $paytype,
                ':paydt' => $invdt,
                ':sbid' => $party_cr_inv,
            ]);
            $payCreditId = (int) $this->pdo->lastInsertId();

            $dupIv = $this->pdo->prepare(
                'SELECT COUNT(*) FROM sale_payment_data WHERE sbid = :sbid AND partyid = :partyid AND custid = :custid
                 AND totamt = :totamt AND paidamt = :paidamt AND paydate = STR_TO_DATE(:paydt, \'%d/%m/%Y\') AND paymode = :paymode'
            );
            $insIv = $this->pdo->prepare(
                'INSERT INTO sale_payment_data (sbid, partyid, custid, totamt, paidamt, paydate, paymode, pcid, pcsbid, cramt, prev_balamt)
                 VALUES (:sbid, :partyid, :custid, :totamt, :paidamt, STR_TO_DATE(:paydt, \'%d/%m/%Y\'), :paymode, :pcid, :pcsbid, :cramt, :prev_balamt)'
            );
            $dupPb = $this->pdo->prepare(
                'SELECT COUNT(*) FROM balance_payment_data WHERE bid = :bid AND partyid = :partyid AND custid = :custid
                 AND totamt = :totamt AND paidamt = :paidamt AND paydate = STR_TO_DATE(:paydt, \'%d/%m/%Y\') AND paymode = :paymode'
            );
            $insPb = $this->pdo->prepare(
                'INSERT INTO balance_payment_data (bid, partyid, custid, totamt, paidamt, paydate, paymode)
                 VALUES (:bid, :partyid, :custid, :totamt, :paidamt, STR_TO_DATE(:paydt, \'%d/%m/%Y\'), :paymode)'
            );

            foreach ($chk as $keyRaw) {
                $key = (string) $keyRaw;
                $chkmode = (string) ($modearr[$key] ?? '');
                $tp = (string) ($tpayamt[$key] ?? '0');
                $payLine = (string) ($payarr[$key] ?? '0');
                $pcLine = (string) ($paycramt[$key] ?? '0');

                if ($chkmode === 'IV') {
                    $totPaidAmt = (float) $payLine;
                    $totCreditAmt = 0.0;
                    if ($party_cr_inv !== '' && (float) $pcLine > 0) {
                        $totPaidAmt = (float) $pcLine;
                        $totCreditAmt = (float) $pcLine;
                    }

                    $dupIv->execute([
                        ':sbid' => $key,
                        ':partyid' => $partyid,
                        ':custid' => $custid,
                        ':totamt' => $tp,
                        ':paidamt' => $totPaidAmt,
                        ':paydt' => $invdt,
                        ':paymode' => $paytype,
                    ]);
                    if ((int) $dupIv->fetchColumn() > 0) {
                        continue;
                    }

                    $insIv->execute([
                        ':sbid' => $key,
                        ':partyid' => $partyid,
                        ':custid' => $custid,
                        ':totamt' => $tp,
                        ':paidamt' => $totPaidAmt,
                        ':paydt' => $invdt,
                        ':paymode' => $paytype,
                        ':pcid' => $payCreditId,
                        ':pcsbid' => $party_cr_inv,
                        ':cramt' => $totCreditAmt,
                        ':prev_balamt' => $prev_balamt,
                    ]);

                    $this->maybeBookProfitForInvoice($key, $partyid);
                } elseif ($chkmode === 'PB') {
                    $paidPb = (float) $payLine;
                    $dupPb->execute([
                        ':bid' => $key,
                        ':partyid' => $partyid,
                        ':custid' => $custid,
                        ':totamt' => $tp,
                        ':paidamt' => $paidPb,
                        ':paydt' => $invdt,
                        ':paymode' => $paytype,
                    ]);
                    if ((int) $dupPb->fetchColumn() > 0) {
                        continue;
                    }
                    $insPb->execute([
                        ':bid' => $key,
                        ':partyid' => $partyid,
                        ':custid' => $custid,
                        ':totamt' => $tp,
                        ':paidamt' => $paidPb,
                        ':paydt' => $invdt,
                        ':paymode' => $paytype,
                    ]);
                }
            }

            $this->pdo->commit();
            $msg = 'Payment Done Successfully.';
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $msg = 'Save failed: ' . $e->getMessage();
        }

        return ['msg' => $msg, 'ctype' => $ctype, 'partyid' => $partyid, 'custid' => $custid];
    }

    private function maybeBookProfitForInvoice(string $sbid, int $partyid): void
    {
        $st = $this->pdo->prepare(
            'SELECT COALESCE(SUM(sp.paidamt), 0) AS totpaid, MAX(s.totamt) AS totamt, MAX(s.totprofitamt) AS totprofitamt
             FROM sale_payment_data sp
             INNER JOIN sale_data s ON s.sbid = sp.sbid
             WHERE sp.sbid = :sbid
             GROUP BY sp.sbid'
        );
        $st->execute([':sbid' => $sbid]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return;
        }
        $totAmt = (float) ($row['totamt'] ?? 0);
        $totPaid = (float) ($row['totpaid'] ?? 0);
        $profitAmt = (float) ($row['totprofitamt'] ?? 0);
        if (round($totAmt - $totPaid, 2) !== 0.0) {
            return;
        }

        $pd = $this->pdo->prepare('SELECT DATE(paydate) AS d FROM sale_payment_data WHERE sbid = :s ORDER BY payid DESC LIMIT 1');
        $pd->execute([':s' => $sbid]);
        $pr = $pd->fetch(\PDO::FETCH_ASSOC);
        if ($pr === false) {
            return;
        }
        $pDate = (string) $pr['d'];

        $chk = $this->pdo->prepare('SELECT pf_id, profit_amt FROM profit_data WHERE partyid = :p AND p_date = :d LIMIT 1');
        $chk->execute([':p' => $partyid, ':d' => $pDate]);
        $ex = $chk->fetch(\PDO::FETCH_ASSOC);
        if ($ex !== false) {
            $newProfit = (float) $ex['profit_amt'] + $profitAmt;
            $up = $this->pdo->prepare('UPDATE profit_data SET profit_amt = :a WHERE pf_id = :id');
            $up->execute([':a' => $newProfit, ':id' => (int) $ex['pf_id']]);
        } else {
            $ins = $this->pdo->prepare('INSERT INTO profit_data (p_date, partyid, profit_amt) VALUES (:d, :p, :a)');
            $ins->execute([':d' => $pDate, ':p' => $partyid, ':a' => $profitAmt]);
        }
    }
}
