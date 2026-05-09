<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CategoryRepository;
use App\Repositories\FuelRepository;
use App\Repositories\PreorderRepository;

/**
 * Staff edit of customer pre-orders (sale_data still flagged as preorder until finalize).
 * Line tax logic matches shop cart (tax_include → GST split).
 */
final class PreorderAdminService
{
    public function __construct(
        private \PDO $pdo,
        private CategoryRepository $catRepo,
        private CustomerDeliveryDateService $delivery,
        private FuelRepository $fuelRepo,
        private PreorderRepository $preorderRepo,
    ) {
    }

    /**
     * @return array{header: array<string, mixed>, lines: list<array<string, mixed>>, partyName: string}|null
     */
    public function loadEditablePreorder(string $sbid): ?array
    {
        $h = $this->preorderRepo->findCustomerPreorderHeader($sbid);
        if ($h === null) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT d.*, m.pname, m.punit AS default_punit FROM sale_product_data d
            LEFT JOIN product_mast m ON m.pid = d.pid
            WHERE d.sbid = :s ORDER BY d.spid ASC'
        );
        $st->execute([':s' => $sbid]);
        $lines = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'header' => $h,
            'lines' => $lines,
            'partyName' => (string) ($h['partynm'] ?? ''),
        ];
    }

    /**
     * @param list<array{pid:int, qty:float, punit:string, rate:float}> $lines
     * @return string empty on success, else error message
     */
    public function savePreorder(string $sbid, string $invdtDmY, string $paymode, string $spnote, string $dvdtKeyOrDmY, array $lines, bool $finalize): string
    {
        $h = $this->preorderRepo->findCustomerPreorderHeader($sbid);
        if ($h === null) {
            return 'Pre-order not found or already converted.';
        }
        if ($lines === []) {
            return 'Add at least one line item.';
        }

        $dvResolved = $this->delivery->resolveDeliveryDate($invdtDmY, $dvdtKeyOrDmY);
        $partyId = (int) ($h['partyid'] ?? 0);

        $lineRows = [];
        $totQty = 0.0;
        $totAmt = 0.0;
        foreach ($lines as $ln) {
            $pid = (int) ($ln['pid'] ?? 0);
            $qty = (float) ($ln['qty'] ?? 0);
            $punit = strtoupper(trim((string) ($ln['punit'] ?? '')));
            $rate = (float) ($ln['rate'] ?? 0);
            if ($pid <= 0 || $qty <= 0 || $punit === '' || $rate <= 0) {
                return 'Each line needs product, quantity, unit, and rate.';
            }
            $amt = round($qty * $rate, 2);
            $taxIn = $this->catRepo->productTaxInclude($pid);
            if ($taxIn === 1) {
                $taxAmt = round($amt / 11, 2);
                $net = round($amt - $taxAmt, 2);
            } else {
                $taxAmt = 0.0;
                $net = $amt;
            }
            $totQty += $qty;
            $totAmt += $amt;
            $lineRows[] = [$pid, $qty, $punit, $rate, $amt, $taxAmt, $net];
        }

        $fuelSum = 0.0;
        foreach ($this->fuelRepo->activeFuelRows() as $fr) {
            $fuelSum += (float) ($fr['fuel_cost'] ?? 0);
        }
        $headerTot = round($totAmt + $fuelSum, 2);
        $paid = 0.0;
        $adv = 0.0;
        $bal = $headerTot;

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('DELETE FROM sale_product_data WHERE sbid = :s')->execute([':s' => $sbid]);
            $ins = $this->pdo->prepare(
                'INSERT INTO sale_product_data (sbid, pid, qty, punit, rate, amt, taxamt, net_amt, sdate, remark)
                VALUES (:sbid, :pid, :qty, :punit, :rate, :amt, :taxamt, :net_amt, STR_TO_DATE(:sdate,\'%d/%m/%Y\'), \'\')'
            );
            foreach ($lineRows as [$pid, $qty, $punit, $rate, $amt, $taxAmt, $net]) {
                $ins->execute([
                    ':sbid' => $sbid,
                    ':pid' => $pid,
                    ':qty' => $qty,
                    ':punit' => $punit,
                    ':rate' => $rate,
                    ':amt' => $amt,
                    ':taxamt' => $taxAmt,
                    ':net_amt' => $net,
                    ':sdate' => $invdtDmY,
                ]);
            }

            $upH = $this->pdo->prepare(
                'UPDATE sale_data SET totqty = :tq, totamt = :ta, paidamt = :pa, advamt = :ad, balamt = :ba,
                    paymode = :pm, sdate = STR_TO_DATE(:sd,\'%d/%m/%Y\'), spnote = :sn,
                    deliverydt = STR_TO_DATE(:dv,\'%d/%m/%Y\')
                    WHERE sbid = :sb AND customer_invoice = \'1\' AND new_cust_invoice = \'1\''
            );
            $upH->execute([
                ':tq' => $totQty,
                ':ta' => $headerTot,
                ':pa' => $paid,
                ':ad' => $adv,
                ':ba' => $bal,
                ':pm' => $paymode,
                ':sd' => $invdtDmY,
                ':sn' => strtoupper(trim($spnote)),
                ':dv' => $dvResolved,
                ':sb' => $sbid,
            ]);
            if ($upH->rowCount() === 0) {
                $this->pdo->rollBack();

                return 'Could not update header (pre-order may have changed).';
            }

            $chkPay = $this->pdo->prepare('SELECT 1 FROM sale_payment_data WHERE sbid = :s LIMIT 1');
            $chkPay->execute([':s' => $sbid]);
            if ($chkPay->fetchColumn()) {
                $upPay = $this->pdo->prepare(
                    'UPDATE sale_payment_data SET totamt = :t, paidamt = :p, paymode = :m,
                        paydate = STR_TO_DATE(:sd,\'%d/%m/%Y\') WHERE sbid = :sb'
                );
                $upPay->execute([
                    ':t' => $headerTot,
                    ':p' => $paid,
                    ':m' => $paymode,
                    ':sd' => $invdtDmY,
                    ':sb' => $sbid,
                ]);
            } else {
                $insPay = $this->pdo->prepare(
                    'INSERT INTO sale_payment_data (sbid, partyid, totamt, paidamt, paydate, paymode)
                    VALUES (:sbid, :pty, :t, :p, STR_TO_DATE(:sd,\'%d/%m/%Y\'), :m)'
                );
                $insPay->execute([
                    ':sbid' => $sbid,
                    ':pty' => $partyId,
                    ':t' => $headerTot,
                    ':p' => $paid,
                    ':sd' => $invdtDmY,
                    ':m' => $paymode,
                ]);
            }

            if ($finalize) {
                $this->preorderRepo->markPreorderConvertedToInvoice($sbid);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return 'Save failed: ' . $e->getMessage();
        }

        return '';
    }

    public function deletePreorder(string $sbid): string
    {
        $h = $this->preorderRepo->findCustomerPreorderHeader($sbid);
        if ($h === null) {
            return 'Pre-order not found or already converted.';
        }
        try {
            $this->pdo->prepare('DELETE FROM sale_payment_data WHERE sbid = :s')->execute([':s' => $sbid]);
            $this->pdo->prepare('DELETE FROM sale_product_data WHERE sbid = :s')->execute([':s' => $sbid]);
            $this->pdo->prepare('DELETE FROM sale_data WHERE sbid = :s')->execute([':s' => $sbid]);
        } catch (\Throwable $e) {
            return 'Delete failed: ' . $e->getMessage();
        }

        return '';
    }

    /** @return list<array{code:string,label:string}> */
    public static function payModeOptions(): array
    {
        return [
            ['code' => 'D', 'label' => '7 DAYS'],
            ['code' => 'F', 'label' => '15 DAYS'],
            ['code' => 'C', 'label' => 'COD'],
            ['code' => 'I', 'label' => '1 IN 1 OUT'],
            ['code' => 'N', 'label' => 'NONE'],
        ];
    }
}
