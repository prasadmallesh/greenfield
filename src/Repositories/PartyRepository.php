<?php

declare(strict_types=1);

namespace App\Repositories;

final class PartyRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPartyCustomerByPartyId(int $partyId): array
    {
        $st = $this->pdo->prepare('SELECT * FROM party_mast WHERE partyid = :id LIMIT 1');
        $st->execute([':id' => $partyId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);

        return $row ? [$row] : [];
    }

    /**
     * Comma-separated party ids for a staff user (union / non-admin scope).
     */
    /**
     * Legacy GetPayModeKey: map party payment terms label to single-letter paymode code.
     */
    public function payModeCodeForParty(int $partyId): string
    {
        $st = $this->pdo->prepare('SELECT pterms FROM party_mast WHERE partyid = :p LIMIT 1');
        $st->execute([':p' => $partyId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        $val = $row ? trim((string) $row['pterms']) : '';
        $arr = ['D' => '7 DAYS', 'F' => '15 DAYS', 'C' => 'COD', 'I' => '1 IN 1 OUT', 'N' => 'NONE'];
        $code = strtoupper($val);
        if ($code !== '' && array_key_exists($code, $arr)) {
            return $code;
        }
        $k = array_search($val, $arr, true);

        return $k !== false ? (string) $k : 'C';
    }

    public function partyIdsCsvForUser(int $uid): ?string
    {
        $st = $this->pdo->prepare(
            "SELECT GROUP_CONCAT(partyid) AS pid FROM party_mast WHERE uid = :uid AND is_active = '1' GROUP BY uid"
        );
        $st->execute([':uid' => $uid]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if ($row === false || $row['pid'] === null || $row['pid'] === '') {
            return null;
        }

        return (string) $row['pid'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPartiesForSelect(): array
    {
        $st = $this->pdo->query(
            "SELECT partyid, partynm FROM party_mast WHERE is_active = '1' ORDER BY partynm ASC LIMIT 2000"
        );

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPartiesScoped(int $uid, string $userType): array
    {
        if ($userType === 'A') {
            return $this->listPartiesForSelect();
        }
        $st = $this->pdo->prepare(
            "SELECT partyid, partynm FROM party_mast WHERE uid = :u AND is_active = '1' ORDER BY partynm ASC LIMIT 2000"
        );
        $st->execute([':u' => $uid]);

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCustomersForSelect(): array
    {
        $st = $this->pdo->query(
            'SELECT custid, custname, custcode FROM customer_mast ORDER BY custname ASC LIMIT 2000'
        );

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listUsersForSelect(): array
    {
        $st = $this->pdo->query(
            'SELECT uid, user_name FROM admin_user ORDER BY user_name ASC LIMIT 500'
        );

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
