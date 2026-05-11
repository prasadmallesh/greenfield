<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deletes sale_payment_data rows (legacy SetRevertInvoicePayment).
 */
final class RevertPaymentService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function deletePayment(string $sbid, int $payid): bool
    {
        $sbid = trim($sbid);
        if ($sbid === '' || $payid <= 0) {
            return false;
        }
        $st = $this->pdo->prepare('DELETE FROM sale_payment_data WHERE sbid = :s AND payid = :p LIMIT 1');

        return $st->execute([':s' => $sbid, ':p' => $payid]) && $st->rowCount() > 0;
    }
}
