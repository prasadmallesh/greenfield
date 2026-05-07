<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Ports legacy SetCustomerSaleData + outstanding hide_invoice rule (no email/WhatsApp here).
 */
final class CustomerCheckoutService
{
    public function __construct(
        private \PDO $pdo,
        private SaleBillNumberService $billNo,
        private CustomerDeliveryDateService $delivery,
    ) {
    }

    /**
     * @param array<string, string|int|float> $post same field names as legacy view-cart form
     * @return array{msg:string,sale_bill_no:string}
     */
    public function finalizeCustomerPreorder(int $partyId, array $post): array
    {
        $out = ['msg' => '', 'sale_bill_no' => ''];
        $sdate = (string) ($post['invdt'] ?? '');
        $totqty = (float) ($post['hd_tot_qty'] ?? 0);
        $totamt = (float) ($post['hd_tot_amt'] ?? 0);
        $paidamt = (float) ($post['hd_paid_amt'] ?? 0);
        $advamt = (float) ($post['advamt'] ?? 0);
        $balamt = (float) ($post['hd_bal_amt'] ?? 0);
        $paytype = (string) ($post['hd_paytype'] ?? '');
        $outstandingAmt = (float) ($post['hd_outstanding_amt'] ?? 0);
        $prodesc = strtoupper(trim((string) ($post['prodesc'] ?? '')));
        $dvRaw = (string) ($post['dvdt'] ?? '');
        $paymethod = (string) ($post['chkpay'] ?? '');

        $pt = $this->partyOutstandingLimit($partyId);
        $outstandingLimit = $pt !== null ? (float) $pt['outstanding_limit_amt'] : 0.0;

        $dvdate = $this->delivery->resolveDeliveryDate($sdate, $dvRaw);
        $saleBillNo = $this->billNo->nextSaleBillId();

        $this->pdo->beginTransaction();
        try {
            $chkTmp = $this->pdo->prepare('SELECT COUNT(*) FROM tmp_customer_sale WHERE partyid = :p');
            $chkTmp->execute([':p' => $partyId]);
            $tmpCount = (int) $chkTmp->fetchColumn();

            $chkSale = $this->pdo->prepare('SELECT COUNT(*) FROM sale_data WHERE sbid = :s');
            $chkSale->execute([':s' => $saleBillNo]);
            $saleExists = (int) $chkSale->fetchColumn();

            if ($tmpCount <= 0 || $saleExists > 0) {
                $this->pdo->rollBack();
                $out['msg'] = 'Cart empty or bill number collision; please refresh.';

                return $out;
            }

            $insSale = $this->pdo->prepare(
                "INSERT INTO sale_data (sbid, partyid, paymode, totqty, totamt, paidamt, advamt, balamt, sdate, spnote,
                    customer_invoice, deliverydt, new_cust_invoice, create_by_customer)
                VALUES (:sbid, :partyid, :paymode, :totqty, :totamt, :paidamt, :advamt, :balamt,
                    STR_TO_DATE(:sdate,'%d/%m/%Y'), :spnote, '1', STR_TO_DATE(:dvdt,'%d/%m/%Y'), '1', '1')"
            );
            $insSale->execute([
                ':sbid' => $saleBillNo,
                ':partyid' => $partyId,
                ':paymode' => $paytype,
                ':totqty' => $totqty,
                ':totamt' => $totamt,
                ':paidamt' => $paidamt,
                ':advamt' => $advamt,
                ':balamt' => $balamt,
                ':sdate' => $sdate,
                ':spnote' => $prodesc,
                ':dvdt' => $dvdate,
            ]);

            $lines = $this->pdo->prepare('SELECT * FROM tmp_customer_sale WHERE partyid = :p');
            $lines->execute([':p' => $partyId]);
            $rows = $lines->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $insLine = $this->pdo->prepare(
                "INSERT INTO sale_product_data (sbid, pid, qty, punit, rate, amt, taxamt, net_amt, sdate, remark)
                VALUES (:sbid, :pid, :qty, :punit, :rate, :amt, :taxamt, :net_amt, STR_TO_DATE(:sdate,'%d/%m/%Y'), :remark)"
            );
            foreach ($rows as $row) {
                $insLine->execute([
                    ':sbid' => $saleBillNo,
                    ':pid' => (int) $row['pid'],
                    ':qty' => (float) $row['qty'],
                    ':punit' => (string) $row['punit'],
                    ':rate' => (float) $row['rate'],
                    ':amt' => (float) $row['amt'],
                    ':taxamt' => (float) $row['taxamt'],
                    ':net_amt' => (float) $row['net_amt'],
                    ':sdate' => $sdate,
                    ':remark' => (string) ($row['remark'] ?? ''),
                ]);
            }

            $totInvAmt = $totamt;
            $insPay = $this->pdo->prepare(
                "INSERT INTO sale_payment_data (sbid, partyid, totamt, paidamt, paydate, paymode)
                VALUES (:sbid, :partyid, :totamt, :paidamt, STR_TO_DATE(:sdate,'%d/%m/%Y'), :paymode)"
            );
            $insPay->execute([
                ':sbid' => $saleBillNo,
                ':partyid' => $partyId,
                ':totamt' => $totInvAmt,
                ':paidamt' => $paidamt,
                ':sdate' => $sdate,
                ':paymode' => $paytype,
            ]);

            $delTmp = $this->pdo->prepare('DELETE FROM tmp_customer_sale WHERE partyid = :p');
            $delTmp->execute([':p' => $partyId]);

            if ($outstandingLimit > 0 && $outstandingAmt > $outstandingLimit) {
                $hid = $this->pdo->prepare("UPDATE party_mast SET hide_invoice = '1' WHERE partyid = :p");
                $hid->execute([':p' => $partyId]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $out['msg'] = 'Save failed: ' . $e->getMessage();

            return $out;
        }

        $out['sale_bill_no'] = $saleBillNo;
        if ($paymethod === 'PP') {
            $out['msg'] = 'DONE';
        } else {
            $out['msg'] = 'Record Added Successfully.';
        }

        return $out;
    }

    /**
     * @return array{outstanding_limit_amt:float}|null
     */
    private function partyOutstandingLimit(int $partyId): ?array
    {
        $st = $this->pdo->prepare('SELECT outstanding_limit_amt FROM party_mast WHERE partyid = :p LIMIT 1');
        $st->execute([':p' => $partyId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
