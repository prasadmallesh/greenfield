<?php

declare(strict_types=1);

namespace App\Repositories;

final class ClientWhatsappRepository
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function resolvePartyIdFromMobile(string $mobDigits): ?int
    {
        $mob = $mobDigits;
        if ($mob !== '' && $mob[0] === '0') {
            $mob = substr($mob, 1);
        }
        $st = $this->pdo->prepare('SELECT partyid FROM client_whatsapp_data WHERE cmob = :m LIMIT 1');
        $st->execute([':m' => $mob]);
        $pid = $st->fetchColumn();
        if ($pid === false) {
            return null;
        }

        return (int) $pid;
    }
}
