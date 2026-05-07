<?php

declare(strict_types=1);

namespace App\Repositories;

final class SaleAccessRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function partyIdForSale(string $sbid): ?int
    {
        $st = $this->pdo->prepare('SELECT partyid FROM sale_data WHERE sbid = :s LIMIT 1');
        $st->execute([':s' => $sbid]);
        $v = $st->fetchColumn();
        if ($v === false) {
            return null;
        }

        return (int) $v;
    }

    public function staffCanAccessSale(string $userType, int $userId, ?string $partyIdsCsv, string $sbid): bool
    {
        $pid = $this->partyIdForSale($sbid);
        if ($pid === null) {
            return false;
        }
        if ($userType === 'A') {
            return true;
        }
        if ($partyIdsCsv === null || $partyIdsCsv === '') {
            return false;
        }
        foreach (array_map('intval', explode(',', $partyIdsCsv)) as $allowed) {
            if ($allowed === $pid) {
                return true;
            }
        }

        return false;
    }

    public function customerOwnsSale(int $customerPartyId, string $sbid): bool
    {
        $pid = $this->partyIdForSale($sbid);

        return $pid !== null && $pid === $customerPartyId;
    }
}
