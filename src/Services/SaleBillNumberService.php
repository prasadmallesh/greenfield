<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Matches legacy GetConfigSaleBillNo / GetSaleBillNo (GREEN + zero-padded numeric part).
 */
final class SaleBillNumberService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function nextSaleBillId(): string
    {
        $st = $this->pdo->query(
            "SELECT MAX(TRIM(LEADING 'GREEN' FROM sbid)) AS sbillno FROM sale_data"
        );
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        $sbillno = (int) ($row['sbillno'] ?? 0);
        if ($sbillno < 1) {
            return 'GREEN000001';
        }

        return $this->formatGreen($sbillno + 1);
    }

    private function formatGreen(int $n): string
    {
        if ($n >= 1 && $n < 10) {
            return 'GREEN00000' . $n;
        }
        if ($n >= 10 && $n < 100) {
            return 'GREEN0000' . $n;
        }
        if ($n >= 100 && $n < 1000) {
            return 'GREEN000' . $n;
        }
        if ($n >= 1000 && $n < 10000) {
            return 'GREEN00' . $n;
        }
        if ($n >= 10000 && $n < 100000) {
            return 'GREEN0' . $n;
        }
        if ($n >= 100000 && $n < 1000000) {
            return 'GREEN' . $n;
        }

        return 'GREEN000001';
    }
}
