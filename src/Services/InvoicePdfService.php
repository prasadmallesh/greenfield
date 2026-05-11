<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Sale invoice PDF aligned with legacy msoft {@see library.php} GetPdfInvoice / SetConfigPdfHead.
 */
final class InvoicePdfService
{
    private const DEFAULT_ASSET_BASE = 'https://greenfarmaccounts.com.au/msoft/images/';

    private const LEGAL_NAME = 'Green Farm Meat NSW Halal';

    public function __construct(private \PDO $pdo)
    {
    }

    public function renderPreorderPdfBinary(string $sbid): string
    {
        $head = $this->fetchSaleHeader($sbid);
        if ($head === null) {
            throw new \InvalidArgumentException('Invoice not found.');
        }

        $lines = $this->fetchSaleLines($sbid);
        $fuelRows = $this->fetchActiveFuelRows();
        $circular = $this->fetchCircularNote();
        $partyId = (int) ($head['partyid'] ?? 0);
        $pbalamt = $this->partyStandingAmount($partyId);
        $bank = $partyId > 0 ? $this->fetchPartyBankDetails($partyId) : ['bsb_no' => '', 'account_no' => ''];

        $ci = (string) ($head['customer_invoice'] ?? '0');
        $nc = (string) ($head['new_cust_invoice'] ?? '0');
        $isPreorder = $ci === '1' && $nc === '1';

        $pm = strtoupper(trim((string) ($head['paymode'] ?? '')));
        $ccredit = (string) ($head['ccredit'] ?? '0');
        $docTitle = $ccredit === '1' ? 'CREDIT NOTE' : 'TAX INVOICE';
        if ($isPreorder) {
            $docTitle .= ' (Preorder)';
        }

        $sdateRaw = $head['sdate'] ?? null;
        $sdt = self::formatDateCell($sdateRaw);
        $tm = self::formatTimeCell($head['create_inv_time'] ?? null);
        $dueDate = self::computeDueDate($sdateRaw, $pm);
        $overdue = self::isOverdue($dueDate);

        $partyName = '';
        $partyAddress = '';
        $partyContact = '';
        $partyPostcode = '';
        $partyAbn = '';
        $deliveryInstr = '---------';
        if ($partyId > 0) {
            $partyName = (string) ($head['partynm'] ?? '');
            $partyAddress = trim((string) ($head['paddress'] ?? '')) . '<br>' . trim((string) ($head['city'] ?? ''));
            $deliveryInstr = trim((string) ($head['deliveryins'] ?? '')) !== '' ? (string) $head['deliveryins'] : '---------';
            $partyContact = (string) ($head['pmob'] ?? '');
            $partyPostcode = (string) ($head['tinno'] ?? '');
            $partyAbn = (string) ($head['abnno'] ?? '');
        } else {
            $partyName = (string) ($head['custname'] ?? '');
            $partyAddress = (string) ($head['caddress'] ?? '');
            $partyContact = (string) ($head['cmob'] ?? '');
        }

        $sper = trim((string) ($head['sper'] ?? ''));
        if ($sper === '' && $partyId > 0) {
            $sper = trim((string) ($head['party_sper'] ?? ''));
        }
        $sperOut = $sper === '' ? '----' : $sper;
        $partyContactOut = $partyContact === '' ? '----' : $partyContact;

        $ototGst = 0.0;
        $ototAmt = 0.0;
        $ototNetAmt = 0.0;
        foreach ($lines as $row) {
            $ototGst += (float) ($row['taxamt'] ?? 0);
            $ototAmt += (float) ($row['amt'] ?? 0);
            $ototNetAmt += (float) ($row['net_amt'] ?? 0);
        }

        $discount = (float) ($head['discount'] ?? 0);
        foreach ($fuelRows as $fr) {
            $ototNetAmt += (float) ($fr['fuel_cost'] ?? 0);
        }

        $assetBase = self::invoiceAssetBase();
        $headHtml = self::pdfHeadHtml($assetBase);
        $legalName = self::h(self::configLegalName());
        $spnote = nl2br(self::h((string) ($head['spnote'] ?? '')), false);

        $html = '<table width="100%" border="0" cellspacing="0" cellpadding="1" style="font-family:Verdana, Geneva, sans-serif; font-size:11px;">';
        $html .= '<tr><td colspan="3" align="center">' . $headHtml . '</td></tr>';

        if ($overdue) {
            $html .= '<tr><td colspan="3" align="center"><table width="100%" cellpadding="1" cellspacing="4" border="0"><tr><td><span style="font-size:16px; font-weight:bold; color:#F00;">Overdue - ' . self::h($dueDate) . '</span></td></tr>';
        } else {
            $html .= '<tr><td colspan="3" align="center"><table width="100%" cellpadding="1" cellspacing="4" border="0"><tr><td><span style="font-size:14px; color:#666;">Due ' . self::h($dueDate) . '</span></td></tr>';
        }
        $html .= '<tr><td><span style="font-size:14px;">' . self::h($docTitle) . ' : ' . self::h($sbid) . '</span></td></tr></table></td></tr>';
        $html .= '<tr><td colspan="3" align="center">&nbsp;</td></tr>';

        if ($overdue) {
            $html .= '<tr><td>&nbsp;</td><td align="center"><table width="100%" cellpadding="12" cellspacing="0" border="0"><tr><td style="background-color:#f5c6cb; border-left:4px solid red; text-align:center; font-weight:bold; font-size:16px;">This invoice is overdue</td></tr></table></td><td>&nbsp;</td></tr>';
            $html .= '<tr><td colspan="3" align="center">&nbsp;</td></tr>';
        }

        $html .= '<tr><td colspan="3" align="center"><table width="98%" border="1" cellspacing="0" cellpadding="2" style="border:#000 solid 1px; border-collapse:collapse;">';
        $html .= '<tr><td align="center" width="50%">&nbsp;<strong>Customer / Party</strong></td><td align="center" width="50%">&nbsp;<strong>Delivery Information</strong></td></tr>';
        $html .= '<tr><td align="left" style="margin:0 2px; font-size:10px;">' . self::h($partyName) . '<br>' . $partyAddress;
        if ($partyPostcode !== '') {
            $html .= '-' . self::h($partyPostcode);
        }
        if ($partyAbn !== '') {
            $html .= '<br /><strong>ABN : </strong>' . self::h($partyAbn);
        }
        $html .= '</td><td align="center" style="margin:0 2px; font-size:10px;">' . self::h($deliveryInstr) . '</td></tr></table></td></tr>';
        $html .= '<tr><td colspan="3" align="center">&nbsp;</td></tr>';

        $html .= '<tr><td colspan="3" align="center"><table width="98%" border="1" cellspacing="0" cellpadding="2" style="border:#000 solid 1px; font-size:10px; border-collapse:collapse;">';
        $html .= '<tr><td align="center" width="10%">&nbsp;<strong>Account No.</strong></td><td align="center" width="20%">&nbsp;<strong>Date</strong></td>';
        $html .= '<td align="center" width="15%">&nbsp;<strong>Contact Person</strong></td><td align="center" width="15%">&nbsp;<strong>Cust. / Party Phone</strong></td>';
        $html .= '<td align="center" width="15%">&nbsp;<strong>Due Date</strong></td><td align="center" width="25%">&nbsp;<strong>Terms</strong></td></tr>';
        $html .= '<tr><td align="center"><div style="margin:0 2px; font-size:10px;">' . self::h((string) ($head['party_accno'] ?? '')) . '</div></td>';
        $html .= '<td align="center"><div style="margin:0 2px; font-size:10px;">' . self::h($sdt . ' ' . $tm) . '</div></td>';
        $html .= '<td align="center"><div style="margin:0 2px; font-size:10px;">' . self::h($sperOut) . '</div></td>';
        $html .= '<td align="center"><div style="margin:0 2px; font-size:10px;">' . self::h($partyContactOut) . '</div></td>';
        $html .= '<td align="center"><div style="margin:0 2px; font-size:10px; color:#F00; font-weight:bold;">' . self::h($dueDate) . '</div></td>';
        $html .= '<td align="center"><div style="margin:0 2px; font-size:10px;">' . self::h(self::payModeLabel($pm)) . '</div></td></tr></table></td></tr>';
        $html .= '<tr><td colspan="3" align="center">&nbsp;</td></tr>';

        $html .= '<tr><td colspan="3" align="center"><table width="98%" border="1" cellspacing="0" cellpadding="2" style="border:#000 solid 1px; font-size:10px; border-collapse:collapse;">';
        $html .= '<tr><td width="40%" height="15" align="center">&nbsp;<strong>Description</strong></td><td align="center" width="15%">&nbsp;<strong>Qty</strong></td>';
        $html .= '<td align="center" width="15%">&nbsp;<strong>Price</strong></td><td align="center" width="15%">&nbsp;<strong>GST</strong></td><td align="center" width="15%">&nbsp;<strong>Amount</strong></td></tr>';
        $html .= '<tr><td align="left" colspan="5" height="350" valign="top"><table width="100%" border="0" cellspacing="0" cellpadding="0">';
        foreach ($lines as $row) {
            $pname = (string) ($row['pname'] ?? '');
            $qty = (string) ($row['qty'] ?? '');
            $punit = (string) ($row['punit'] ?? '');
            $rate = (float) ($row['rate'] ?? 0);
            $tax = (float) ($row['taxamt'] ?? 0);
            $amt = (float) ($row['amt'] ?? 0);
            $html .= '<tr><td align="left" width="40%" height="17"><div style="margin:0 2px; font-size:10px;">' . self::h($pname) . '</div></td>';
            $html .= '<td align="center" width="15%"><div style="margin:0 2px; font-size:10px;">' . self::h($qty . ' ' . $punit) . '</div></td>';
            $html .= '<td align="center" width="15%"><div style="margin:0 2px; font-size:10px;">$' . self::h(number_format($rate, 2, '.', '')) . '</div></td>';
            $html .= '<td align="center" width="15%"><div style="margin:0 2px; font-size:10px;">$' . self::h(number_format($tax, 2, '.', '')) . '</div></td>';
            $html .= '<td align="center" width="15%"><div style="margin:0 2px; font-size:10px;">$' . self::h(number_format($amt, 2, '.', '')) . '</div></td></tr>';
        }
        $html .= '<tr><td colspan="5" align="center" style="padding:4px 0; font-size:12px; font-weight:bold; color:#F00;">';
        if ($circular !== null && (string) ($circular['is_active'] ?? '0') === '1' && trim((string) ($circular['description'] ?? '')) !== '') {
            $html .= '<br /><br />' . nl2br(self::h((string) $circular['description']), false);
        }
        $html .= '&nbsp;</td></tr></table></td></tr>';
        $html .= '<tr><td colspan="5" align="left" style="margin:2px; line-height:18px;"><strong>Special Notes : </strong>' . $spnote . '</td></tr>';
        $html .= '<tr><td colspan="3" rowspan="3" align="left" valign="top" style="margin:2px; line-height:18px;"><strong>Pay Terms : ' . self::h(self::payModeLabel($pm)) . '</strong><br /><strong>Standing/Running Amount : $' . self::h(number_format($pbalamt, 2, '.', '')) . '</strong></td>';
        $html .= '<td align="right"><strong>Sub-Total</strong>&nbsp;</td><td align="center">$' . self::h(number_format($ototAmt, 2, '.', '')) . '</td></tr>';
        $html .= '<tr><td align="right"><strong>G.S.T.</strong>&nbsp;</td><td align="center">$' . self::h(number_format($ototGst, 2, '.', '')) . '</td></tr>';
        if ($discount > 0) {
            $html .= '<tr><td align="right"><strong>Discount</strong></td><td align="center">$' . self::h(number_format($discount, 2, '.', '')) . '</td></tr>';
        }
        foreach ($fuelRows as $fr) {
            $fname = (string) ($fr['fuel_name'] ?? '');
            $fcost = (float) ($fr['fuel_cost'] ?? 0);
            $html .= '<tr><td align="right"><strong>' . self::h($fname) . '</strong>&nbsp;</td><td align="center">$' . self::h(number_format($fcost, 2, '.', '')) . '</td></tr>';
        }
        $totalVal = $ototNetAmt - $discount;
        $html .= '<tr><td align="right"><strong>Total</strong>&nbsp;</td><td align="center">$' . self::h(number_format($totalVal, 2, '.', '')) . '</td></tr>';
        $html .= '</table></td></tr>';
        $html .= '<tr><td colspan="3" align="center">&nbsp;</td></tr>';

        $bsbPart = !empty($bank['bsb_no']) ? '<strong>BSB : </strong>' . self::h((string) $bank['bsb_no']) : '';
        $acctPart = !empty($bank['account_no']) ? '<strong>Account : </strong>' . self::h((string) $bank['account_no']) : '';
        $html .= '<tr><td colspan="3" align="center"><table width="98%" border="1" cellspacing="0" cellpadding="2" style="border:#000 solid 1px; border-collapse:collapse;">';
        $html .= '<tr><td align="left" width="70%" valign="top"><div style="margin:0 2px; line-height:18px; font-size:11px;"><strong>Terms &amp; Conditions:</strong><br />Interest chargeable on overdue accounts. No claims recognised after time of delivery. E. &amp; O.E.<br />Goods remain property of ' . $legalName . '. Unpaid invoices attract all collection costs.<br />We accept major credit cards or direct deposit to:<br /><strong>Bank :</strong> ANZ&nbsp;&nbsp;&nbsp;' . $bsbPart . '&nbsp;&nbsp;&nbsp;' . $acctPart . '<br />(Add 2% if paying by Visa/Master Card or 4% by Amex)</div></td>';
        $html .= '<td align="left" width="30%" valign="top"><div style="margin:0 2px; line-height:25px; font-size:11px;"><strong>Name&nbsp;&nbsp;&nbsp;</strong>...................................<br /><strong>Sign&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</strong>...................................<br /><strong>Date&nbsp;&amp;Times&nbsp;</strong>..................................<br /><strong>Temp.&nbsp;&nbsp;&nbsp;</strong>..................................</div></td></tr></table></td></tr>';
        $html .= '<tr><td colspan="3" align="center"><hr /><p align="center" style="font-size:9px;">&copy; Developed by E-Soft</p></td></tr>';
        $html .= '</table>';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Greenfield');
        $pdf->SetTitle(($isPreorder ? 'Preorder ' : 'Invoice ') . $sbid);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 9);
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S');
    }

    /** @return array<string, mixed>|null */
    private function fetchSaleHeader(string $sbid): ?array
    {
        $sql = "SELECT s.*, p.partynm, p.address AS paddress, p.city, p.contactno AS pmob, p.sper AS party_sper,
            p.deliveryins, p.tinno, p.abnno, p.party_accno,
            c.custname, c.address AS caddress, c.contactno AS cmob
            FROM sale_data s
            LEFT JOIN party_mast p ON s.partyid = p.partyid
            LEFT JOIN customer_mast c ON s.custid = c.custid
            WHERE s.sbid = :s LIMIT 1";
        $h = $this->pdo->prepare($sql);
        $h->execute([':s' => $sbid]);
        $row = $h->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    private function fetchSaleLines(string $sbid): array
    {
        $l = $this->pdo->prepare(
            'SELECT d.*, m.pname FROM sale_product_data d
            LEFT JOIN product_mast m ON m.pid = d.pid
            WHERE d.sbid = :s ORDER BY d.pid ASC'
        );
        $l->execute([':s' => $sbid]);

        return $l->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    private function fetchActiveFuelRows(): array
    {
        try {
            $st = $this->pdo->query("SELECT fuel_name, fuel_cost FROM fuel_master WHERE is_active = '1'");

            return $st === false ? [] : ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{description:string,is_active:string}|null */
    private function fetchCircularNote(): ?array
    {
        try {
            $st = $this->pdo->query('SELECT description, is_active FROM circular_data LIMIT 1');
            if ($st === false) {
                return null;
            }
            $row = $st->fetch(\PDO::FETCH_ASSOC);

            return $row === false ? null : $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{bsb_no:string,account_no:string} */
    private function fetchPartyBankDetails(int $partyId): array
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT a.bsb_no, a.account_no FROM party_mast p
                LEFT JOIN admin_user a ON p.uid = a.uid WHERE p.partyid = :p LIMIT 1'
            );
            $st->execute([':p' => $partyId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                return ['bsb_no' => '', 'account_no' => ''];
            }

            return [
                'bsb_no' => trim((string) ($row['bsb_no'] ?? '')),
                'account_no' => trim((string) ($row['account_no'] ?? '')),
            ];
        } catch (\Throwable) {
            return ['bsb_no' => '', 'account_no' => ''];
        }
    }

    private function partyStandingAmount(int $partyId): float
    {
        if ($partyId <= 0) {
            return 0.0;
        }
        $bs = new BillSettlementService($this->pdo);
        $stand = 0.0;
        foreach ($bs->getOutstandingInvoiceRows('', '', $partyId, 0, '', null) as $r) {
            $stand += round((float) $r['invtotamt'] - (float) $r['invpayamt'], 2);
        }
        $ob = $bs->getOpeningBalanceRow('P', $partyId, 0);
        if ($ob !== null) {
            $stand += round((float) $ob['baltotamt'] - (float) $ob['balpayamt'], 2);
        }
        try {
            $st = $this->pdo->prepare('SELECT cramt FROM party_credit_amt WHERE partyid = :p LIMIT 1');
            $st->execute([':p' => $partyId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row !== false && isset($row['cramt'])) {
                $stand -= (float) $row['cramt'];
            }
        } catch (\Throwable) {
        }

        return round($stand, 2);
    }

    private static function invoiceAssetBase(): string
    {
        $b = trim((string) ($_ENV['INVOICE_PDF_ASSET_BASE'] ?? getenv('INVOICE_PDF_ASSET_BASE') ?: ''));
        if ($b === '') {
            $b = self::DEFAULT_ASSET_BASE;
        }
        if (!str_ends_with($b, '/')) {
            $b .= '/';
        }

        return $b;
    }

    private static function configLegalName(): string
    {
        $n = trim((string) ($_ENV['INVOICE_LEGAL_NAME'] ?? getenv('INVOICE_LEGAL_NAME') ?: ''));

        return $n !== '' ? $n : self::LEGAL_NAME;
    }

    private static function pdfHeadHtml(string $assetBase): string
    {
        $b = self::h($assetBase);

        return '<table cellspacing="1" cellpadding="1" border="0">
            <tr><td colspan="3" align="center"><img src="' . $b . 'print-logo.jpg" /></td></tr>
            <tr>
                <td align="center" width="25%"><img src="' . $b . 'graded.png" border="0" /></td>
                <td align="center" width="45%"><span style="font-size:11px;"><strong>ABN : </strong>24 604 692 435<br />
                  838 A, Pittwater Road, NSW - 2099<br>
                  <strong>Ph : </strong>(02) 9972 9785 <strong>Mobile : </strong>0431 695 222<br />
                  <strong>Email : </strong>actionrequiredoverdueinvoice@gmail.com<br />
                  <strong>Website : www.greenfarmmeatnswhalal.com.au</strong></span></td>
                <td align="center" width="15%"><img src="' . $b . 'grown.png" border="0" /></td>
                <td align="center" width="15%"><img src="' . $b . 'haccp.png" border="0" /></td>
            </tr>
            </table>';
    }

    private static function h(string $s): string
    {
        return htmlspecialchars($s, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function formatDateCell(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        $s = trim((string) $v);
        if ($s === '' || str_starts_with($s, '0000')) {
            return '—';
        }
        $t = strtotime($s);

        return $t ? date('d/m/Y', $t) : $s;
    }

    private static function formatTimeCell(mixed $v): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $s = trim((string) $v);
        if ($s === '' || $s === '00:00:00') {
            return '';
        }
        $t = strtotime('1970-01-01 ' . $s);

        return $t ? date('h:i A', $t) : '';
    }

    private static function computeDueDate(mixed $sdateRaw, string $pm): string
    {
        $base = self::formatDateCell($sdateRaw);
        if ($sdateRaw === null || $sdateRaw === '') {
            return $base;
        }
        $ts = strtotime((string) $sdateRaw);
        if ($ts === false) {
            return $base;
        }
        if ($pm === 'D') {
            return date('d/m/Y', strtotime('+7 day', $ts));
        }
        if ($pm === 'F') {
            return date('d/m/Y', strtotime('+15 day', $ts));
        }

        return $base;
    }

    private static function isOverdue(string $dueDmy): bool
    {
        if ($dueDmy === '—' || $dueDmy === '') {
            return false;
        }
        $d1 = \DateTime::createFromFormat('d/m/Y', $dueDmy);
        if ($d1 === false) {
            return false;
        }
        $d2 = new \DateTime('today');

        return $d1 < $d2;
    }

    private static function payModeLabel(string $code): string
    {
        $m = [
            'D' => '7 DAYS',
            'F' => '15 DAYS',
            'C' => 'COD',
            'I' => '1 IN 1 OUT',
            'N' => 'NONE',
        ];

        return $m[$code] ?? ($code !== '' ? $code : '----');
    }
}
