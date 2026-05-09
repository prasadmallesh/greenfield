<?php

declare(strict_types=1);

namespace App\Services;

final class InvoicePdfService
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Minimal customer pre-invoice PDF (lines from sale_product_data).
     */
    public function renderPreorderPdfBinary(string $sbid): string
    {
        $h = $this->pdo->prepare(
            'SELECT s.*, p.partynm FROM sale_data s
            LEFT JOIN party_mast p ON s.partyid = p.partyid WHERE s.sbid = :s LIMIT 1'
        );
        $h->execute([':s' => $sbid]);
        $head = $h->fetch(\PDO::FETCH_ASSOC);
        if ($head === false) {
            throw new \InvalidArgumentException('Invoice not found.');
        }

        $l = $this->pdo->prepare(
            'SELECT d.*, m.pname FROM sale_product_data d
            LEFT JOIN product_mast m ON m.pid = d.pid
            WHERE d.sbid = :s ORDER BY d.pid ASC'
        );
        $l->execute([':s' => $sbid]);
        $lines = $l->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $ci = (string) ($head['customer_invoice'] ?? '0');
        $nc = (string) ($head['new_cust_invoice'] ?? '0');
        $isPreorder = $ci === '1' && $nc === '1';
        $docHeading = $isPreorder ? 'Preorder / invoice draft' : 'Invoice';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Greenfield msoft');
        $pdf->SetTitle(($isPreorder ? 'Preorder ' : 'Invoice ') . $sbid);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Write(0, $docHeading, '', false, 'L', true);
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Write(0, 'Bill: ' . $sbid, '', false, 'L', true);
        $pdf->Write(0, 'Party: ' . ($head['partynm'] ?? ''), '', false, 'L', true);
        $pm = strtoupper(trim((string) ($head['paymode'] ?? '')));
        $pdf->Write(0, 'Payment terms: ' . self::payModeLabel($pm), '', false, 'L', true);
        $pdf->Write(0, 'Order date: ' . self::formatDateCell($head['sdate'] ?? null), '', false, 'L', true);
        $pdf->Write(0, 'Delivery: ' . self::formatDateCell($head['deliverydt'] ?? null), '', false, 'L', true);
        $note = trim((string) ($head['spnote'] ?? ''));
        if ($note !== '') {
            $pdf->Write(0, 'Note: ' . $note, '', false, 'L', true);
        }
        $pdf->Ln(6);
        $html = '<table border="1" cellpadding="3"><thead><tr style="background-color:#0c6932;color:#fff;">
            <th>Product</th><th align="right">Qty</th><th align="right">Rate</th><th align="right">Amount</th></tr></thead><tbody>';
        foreach ($lines as $row) {
            $html .= '<tr><td>' . htmlspecialchars((string) ($row['pname'] ?? ''), \ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td align="right">' . htmlspecialchars((string) ($row['qty'] ?? ''), \ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars((string) ($row['punit'] ?? ''), \ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td align="right">' . htmlspecialchars((string) ($row['rate'] ?? ''), \ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td align="right">' . htmlspecialchars((string) ($row['net_amt'] ?? ''), \ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $html .= '</tbody></table>';
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Write(0, 'Total: ' . ($head['totamt'] ?? ''), '', false, 'R', true);

        return $pdf->Output('', 'S');
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

    private static function payModeLabel(string $code): string
    {
        $m = [
            'D' => '7 DAYS',
            'F' => '15 DAYS',
            'C' => 'COD',
            'I' => '1 IN 1 OUT',
            'N' => 'NONE',
        ];

        return $m[$code] ?? ($code !== '' ? $code : '—');
    }
}
